<?php

namespace MunguiaEr\LaravelCleanGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Database\Connection;
use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;
use MunguiaEr\LaravelCleanGenerator\Generators\ModelGenerator;
use MunguiaEr\LaravelCleanGenerator\Generators\DtoGenerator;
use MunguiaEr\LaravelCleanGenerator\Generators\RepositoryGenerator;
use MunguiaEr\LaravelCleanGenerator\Generators\ServiceGenerator;
use MunguiaEr\LaravelCleanGenerator\Generators\ControllerGenerator;
use MunguiaEr\LaravelCleanGenerator\AST\ProviderModifier;
use MunguiaEr\LaravelCleanGenerator\AST\RouteModifier;
use MunguiaEr\LaravelCleanGenerator\Support\PathResolver;
use Exception;

class GenerateCleanCrudCommand extends Command
{
    protected $signature = 'clean:generate
                            {model : The Model to generate architecture for (e.g. Blog/Post)}
                            {--table= : The database table name (required when model does not exist)}
                            {--api : Generate an API controller with JSON responses (files under Api/ sub-path)}
                            {--web : Generate a Web controller with HTML responses}
                            {--force : Overwrite any existing files}';

    protected $description = 'Generate scaffolding files for a given model according to the configured architecture (simple | clean)';

    public function handle(): int
    {
        $modelInput = $this->argument('model');
        $tableInput = $this->option('table');
        $isApi      = (bool) $this->option('api');
        $isWeb      = (bool) $this->option('web');
        $force      = (bool) $this->option('force');

        // ── Guard: --api and --web are mutually exclusive ─────────────────────
        if ($isApi && $isWeb) {
            $this->error('You cannot use --api and --web together. Choose one.');
            return self::FAILURE;
        }

        // ── Default: use configured stack when no flag given ──────────────────
        if (!$isApi && !$isWeb) {
            $defaultStack = config('clean-generator.default_stack', 'web');
            $isApi = ($defaultStack === 'api');
            $isWeb = !$isApi;
        }

        $arch = config('clean-generator.architecture', 'clean');
        $this->info("Starting generation for: <options=bold>{$modelInput}</> [arch: {$arch}, stack: " . ($isApi ? 'api' : 'web') . ']');

        try {
            $connection   = app(Connection::class);
            $introspector = new SchemaIntrospector($connection);

            // ── Resolve effective inputs ──────────────────────────────────────
            //
            // When --api is used, ALL generated layers are placed under an
            // additional "Api/" sub-path within the module.
            //
            // Model is special: if the class already exists, we keep it in
            // its current location and only apply the Api/ prefix to the
            // remaining layers.
            //
            $effectiveInput = $isApi
                ? $this->withApiPrefix($modelInput)
                : $modelInput;

            // ── Step 1: Resolve model existence & table ───────────────────────
            $tableName   = null;
            $modelExists = false;
            $generateModel = false;

            $this->components->task('Resolving Model / Table', function () use (
                $modelInput, $effectiveInput, $tableInput, $introspector, $isApi, $force,
                &$tableName, &$modelExists, &$generateModel
            ) {
                $modelName = class_basename($modelInput);

                // Check the model at its ORIGINAL (non-prefixed) location —
                // class_exists() covers autoloaded classes; file_exists() covers
                // models that live on disk but haven't been loaded yet (test envs).
                $originalModelNs   = PathResolver::for($modelInput)->forType('models')->namespace();
                $originalModelClass = $originalModelNs . '\\' . $modelName;
                $modelFilePath     = PathResolver::for($modelInput)->forType('models')->file($modelName . '.php');

                $modelExists = class_exists($originalModelClass) || file_exists($modelFilePath);

                if ($tableInput) {
                    // --table provided → always (re)generate the model
                    $tableName     = $tableInput;
                    $generateModel = true;
                } else {
                    if (!$modelExists) {
                        return false; // Stops task; guard below will abort command
                    }

                    // Model exists: derive table from convention
                    $tableName     = Str::snake(Str::pluralStudly($modelName));
                    $generateModel = false; // Never overwrite an existing model
                }

                return true;
            });

            // ── Guard: model missing + no --table ─────────────────────────────
            if (!$modelExists && !$tableInput) {
                $modelName = class_basename($modelInput);
                $modelNs   = PathResolver::for($modelInput)->forType('models')->namespace();
                $this->error(
                    "Model [{$modelNs}\\{$modelName}] does not exist and --table option was not provided."
                );
                return self::FAILURE;
            }

            // Validate that the table actually exists in the DB
            $introspector->getTableInfo($tableName);

            // ── Decide which input to use for the model ───────────────────────
            // If model exists → leave it in its original location (no Api/ prefix).
            // If generating fresh from --table → apply full effective input.
            $modelInput4Gen = ($generateModel && $isApi) ? $effectiveInput : $modelInput;

            // ── Step 2: Generate Model (only when --table given) ─────────────
            if ($generateModel) {
                $this->components->task('Generating Model', function () use ($introspector, $modelInput4Gen, $tableName, $force) {
                    (new ModelGenerator($introspector))->generate($modelInput4Gen, $tableName, $force);
                    return true;
                });
            }

            // ── Step 3: DTOs ──────────────────────────────────────────────────
            $this->components->task('Generating DTOs', function () use ($introspector, $effectiveInput, $tableName, $force) {
                (new DtoGenerator($introspector))->generate($effectiveInput, $tableName, $force);
                return true;
            });

            // ── Step 4: Repository ────────────────────────────────────────────
            $repoClasses = [];
            $this->components->task('Generating Repository', function () use (&$repoClasses, $introspector, $effectiveInput, $tableName, $force) {
                $repoClasses = (new RepositoryGenerator($introspector))->generate($effectiveInput, $tableName, $force);
                return true;
            });

            // ── Step 5: Service ───────────────────────────────────────────────
            $serviceClasses = [];
            $this->components->task('Generating Service', function () use (&$serviceClasses, $introspector, $effectiveInput, $tableName, $force) {
                $serviceClasses = (new ServiceGenerator($introspector))->generate($effectiveInput, $tableName, $force);
                return true;
            });

            // ── Step 6: Controller ────────────────────────────────────────────
            $controllerFqcn = null;
            $this->components->task('Generating Controller', function () use (&$controllerFqcn, $introspector, $effectiveInput, $isApi, $force) {
                $controllerFqcn = (new ControllerGenerator($introspector))->generateController($effectiveInput, $isApi, $force);
                return true;
            });

            // ── Step 7: Register provider binding ─────────────────────────────
            if (config('clean-generator.auto_register.bindings', true)) {
                $this->components->task('Registering Bindings', function () use ($repoClasses) {
                    $providerPath = app_path('Providers/AppServiceProvider.php');
                    if (file_exists($providerPath)) {
                        (new ProviderModifier())->addBinding(
                            $providerPath,
                            $repoClasses['interface'],
                            $repoClasses['implementation']
                        );
                    }
                    return true;
                });
            }

            // ── Step 8: Register route ────────────────────────────────────────
            if ($controllerFqcn && config('clean-generator.auto_register.routes', true)) {
                $this->components->task('Registering Route', function () use ($isApi, $controllerFqcn, $tableName) {
                    $routeFile = base_path('routes/' . ($isApi ? 'api.php' : 'web.php'));
                    $routeName = Str::kebab($tableName);
                    (new RouteModifier())->addResourceRoute($routeFile, $controllerFqcn, $routeName, $isApi);
                    return true;
                });
            }

            $this->newLine();
            $this->info('✅ Scaffolding completed successfully!');
            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('Generation Failed: ' . $e->getMessage());
            if (app()->runningUnitTests()) {
                throw $e;
            }
            return self::FAILURE;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Inject "Api" as the first sub-path segment of the module.
     *
     * Examples:
     *   Post         → Api/Post
     *   Blog/Post    → Api/Blog/Post
     *   Blog/Admin/Post → Api/Blog/Admin/Post
     */
    protected function withApiPrefix(string $input): string
    {
        $segments  = explode('/', trim($input, '/'));
        $modelName = array_pop($segments);        // last segment = class name

        // Prepend Api to the module path (not the class name)
        array_unshift($segments, 'Api');

        // Rebuild: module segments + class name
        return implode('/', array_filter([...$segments, $modelName]));
    }
}
