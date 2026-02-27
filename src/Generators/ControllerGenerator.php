<?php

namespace MunguiaEr\LaravelCleanGenerator\Generators;

use MunguiaEr\LaravelCleanGenerator\Support\PathResolver;

class ControllerGenerator extends AbstractGenerator
{
    public function generate(string $input, string $tableName, bool $force = false): mixed
    {
        // Fulfills abstract contract — concrete generation is done via generateController()
        return null;
    }

    /**
     * Generate a controller file.
     *
     * @param  string $input   Module input, e.g. 'Blog/Post'
     * @param  bool   $isApi   true = API (JSON), false = Web (HTML)
     * @param  bool   $force   Overwrite existing file
     * @return string          Fully-qualified class name of the generated controller
     */
    public function generateController(string $input, bool $isApi, bool $force = false): string
    {
        $modelName = class_basename(str_replace('/', '\\', $input));

        $controllerResolver = PathResolver::for($input)->forType('controllers');
        $appDir             = $controllerResolver->path();
        $this->ensureDirectoryExists($appDir);

        $controllerNamespace = $controllerResolver->namespace();

        // Service interface lives under 'service_interfaces'
        $serviceInterfaceNamespace = PathResolver::for($input)->forType('service_interfaces')->namespace();
        $dtoNamespace              = PathResolver::for($input)->forType('dtos')->namespace();

        // Controller name — no Api prefix for web controllers
        $controllerName = $isApi
            ? "Api{$modelName}Controller"
            : "{$modelName}Controller";

        $controllerPath = $appDir . '/' . $controllerName . '.php';

        if (!file_exists($controllerPath) || $force) {
            // Select the correct stub
            $stubName = $isApi ? 'controller-api' : 'controller-web';

            $stub = $this->getStub($stubName);
            $stub = str_replace('{{ namespace }}',             $controllerNamespace,                                               $stub);
            $stub = str_replace('{{ class }}',                 $controllerName,                                                    $stub);
            $stub = str_replace('{{ modelName }}',             $modelName,                                                         $stub);
            $stub = str_replace('{{ serviceInterfaceName }}',  $serviceInterfaceNamespace . '\\' . $modelName . 'ServiceInterface', $stub);
            $stub = str_replace('{{ serviceInterfaceClass }}', $modelName . 'ServiceInterface',                                    $stub);
            $stub = str_replace('{{ createDtoName }}',         $dtoNamespace . '\\Create' . $modelName . 'Data',                  $stub);
            $stub = str_replace('{{ updateDtoName }}',         $dtoNamespace . '\\Update' . $modelName . 'Data',                  $stub);
            $stub = str_replace('{{ createDtoClass }}',        'Create' . $modelName . 'Data',                                    $stub);
            $stub = str_replace('{{ updateDtoClass }}',        'Update' . $modelName . 'Data',                                    $stub);

            file_put_contents($controllerPath, $stub);
        }

        return $controllerNamespace . '\\' . $controllerName;
    }
}
