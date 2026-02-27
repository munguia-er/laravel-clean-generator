<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Feature;

use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

/**
 * End-to-end tests for the clean:generate command.
 *
 * Cases A–E (from user spec) plus additional assertions.
 */
class GenerateCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tearDownTempDirectory();
        $this->setupTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTempDirectory();
        parent::tearDown();
    }

    // ── Guard: model missing + no --table ────────────────────────────────────

    /** @test */
    public function it_fails_when_model_does_not_exist_and_table_is_not_provided()
    {
        $exitCode = Artisan::call('clean:generate', [
            'model' => 'NonExistent',
        ]);

        $this->assertEquals(1, $exitCode);

        $appPath = __DIR__ . '/../temp/app';
        $this->assertDirectoryDoesNotExist($appPath . '/DTOs');
        $this->assertDirectoryDoesNotExist($appPath . '/Domain');
    }

    // ── Guard: --api + --web cannot coexist ──────────────────────────────────

    /** @test */
    public function it_fails_when_both_api_and_web_flags_are_provided()
    {
        $exitCode = Artisan::call('clean:generate', [
            'model'   => 'Blog/Post',
            '--table' => 'posts',
            '--api'   => true,
            '--web'   => true,
        ]);

        $this->assertEquals(1, $exitCode);
    }

    // ── Case A: No flag → uses default_stack (web) ───────────────────────────

    /** @test */
    public function case_a_no_flag_uses_default_stack_web()
    {
        // default_stack is 'web' in TestCase; no --api / --web provided
        $exitCode = Artisan::call('clean:generate', [
            'model'   => 'Blog/Post',
            '--table' => 'posts',
        ]);

        $this->assertEquals(0, $exitCode);

        $appPath = __DIR__ . '/../temp/app';

        // Web: no Api/ prefix
        $this->assertFileExists($appPath . '/Domain/DTOs/Blog/PostData.php');
        $this->assertFileExists($appPath . '/Application/Http/Controllers/Blog/PostController.php');

        // Should NOT contain the Api/ prefix
        $this->assertDirectoryDoesNotExist($appPath . '/Domain/DTOs/Api');
        $this->assertDirectoryDoesNotExist($appPath . '/Application/Http/Controllers/Api');
    }

    // ── Case B: --api injects Api/ prefix ────────────────────────────────────

    /** @test */
    public function case_b_api_flag_generates_files_under_api_prefix()
    {
        $exitCode = Artisan::call('clean:generate', [
            'model'   => 'Blog/Post',
            '--table' => 'posts',
            '--api'   => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $appPath = __DIR__ . '/../temp/app';

        // All layers are under Api/Blog
        $this->assertFileExists($appPath . '/Domain/DTOs/Api/Blog/PostData.php');
        $this->assertFileExists($appPath . '/Domain/DTOs/Api/Blog/CreatePostData.php');
        $this->assertFileExists($appPath . '/Domain/DTOs/Api/Blog/UpdatePostData.php');
        $this->assertFileExists($appPath . '/Domain/Contracts/Api/Blog/PostRepositoryInterface.php');
        $this->assertFileExists($appPath . '/Infrastructure/Repositories/Api/Blog/EloquentPostRepository.php');
        $this->assertFileExists($appPath . '/Domain/Services/Api/Blog/PostServiceInterface.php');
        $this->assertFileExists($appPath . '/Domain/Services/Api/Blog/PostService.php');
        $this->assertFileExists($appPath . '/Application/Http/Controllers/Api/Blog/ApiPostController.php');

        // Namespaces must reflect the Api/ sub-path
        $dto = file_get_contents($appPath . '/Domain/DTOs/Api/Blog/PostData.php');
        $this->assertStringContainsString('namespace App\Domain\DTOs\Api\Blog;', $dto);

        $controller = file_get_contents($appPath . '/Application/Http/Controllers/Api/Blog/ApiPostController.php');
        $this->assertStringContainsString('namespace App\Application\Http\Controllers\Api\Blog;', $controller);
        $this->assertStringContainsString('return response()->json', $controller);

        // Route must be registered
        $routeContent = file_get_contents(__DIR__ . '/../temp/routes/api.php');
        $this->assertStringContainsString('App\Application\Http\Controllers\Api\Blog\ApiPostController', $routeContent);
    }

    // ── Case C: Model exists → not moved when --api ───────────────────────────

    /** @test */
    public function case_c_existing_model_is_not_moved_when_using_api_flag()
    {
        // Pre-create the model at its original (non-Api/) location
        $modelsDir = __DIR__ . '/../temp/app/Models/Blog';
        @mkdir($modelsDir, 0777, true);
        file_put_contents($modelsDir . '/Post.php', "<?php\nnamespace App\\Models\\Blog;\nclass Post extends \\Illuminate\\Database\\Eloquent\\Model {}");

        // Run with --api but without --table (model already exists)
        $exitCode = Artisan::call('clean:generate', [
            'model'  => 'Blog/Post',
            '--api'  => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $appPath = __DIR__ . '/../temp/app';

        // Model must stay at its original location
        $this->assertFileExists($appPath . '/Models/Blog/Post.php');
        $this->assertDirectoryDoesNotExist($appPath . '/Models/Api');

        // All OTHER generated files go under Api/Blog
        $this->assertFileExists($appPath . '/Domain/DTOs/Api/Blog/PostData.php');
        $this->assertFileExists($appPath . '/Application/Http/Controllers/Api/Blog/ApiPostController.php');
    }

    // ── Case D: --web → no Api/ prefix ───────────────────────────────────────

    /** @test */
    public function case_d_web_flag_does_not_add_api_prefix()
    {
        $exitCode = Artisan::call('clean:generate', [
            'model'   => 'Blog/Post',
            '--table' => 'posts',
            '--web'   => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $appPath = __DIR__ . '/../temp/app';

        // Web path — no Api/ prefix
        $this->assertFileExists($appPath . '/Domain/DTOs/Blog/PostData.php');
        $this->assertFileExists($appPath . '/Application/Http/Controllers/Blog/PostController.php');
        $this->assertDirectoryDoesNotExist($appPath . '/Domain/DTOs/Api');
    }

    // ── Case E: default_stack = api → generates under Api/ ───────────────────

    /** @test */
    public function case_e_default_stack_api_generates_under_api_prefix()
    {
        Config::set('clean-generator.default_stack', 'api');

        $exitCode = Artisan::call('clean:generate', [
            'model'   => 'Blog/Post',
            '--table' => 'posts',
        ]);

        $this->assertEquals(0, $exitCode);

        $appPath = __DIR__ . '/../temp/app';

        // Api/ prefix applied even without --api flag
        $this->assertFileExists($appPath . '/Domain/DTOs/Api/Blog/PostData.php');
        $this->assertFileExists($appPath . '/Application/Http/Controllers/Api/Blog/ApiPostController.php');
    }

    // ── Service calls in GET methods ─────────────────────────────────────────

    /** @test */
    public function it_generates_web_controller_with_html_responses_and_service_calls()
    {
        Artisan::call('clean:generate', [
            'model'   => 'Blog/Post',
            '--table' => 'posts',
            '--web'   => true,
        ]);

        $controllerPath = __DIR__ . '/../temp/app/Application/Http/Controllers/Blog/PostController.php';
        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        // HTML responses — no JSON
        $this->assertStringContainsString('<h1>index method called</h1>', $content);
        $this->assertStringNotContainsString('response()->json', $content);

        // Service calls in GET-ish methods
        $this->assertStringContainsString('$this->service->paginate()', $content); // index + create
        $this->assertStringContainsString('$this->service->find($id)',   $content); // show + edit
    }

    /** @test */
    public function it_generates_api_controller_with_json_responses_and_service_calls()
    {
        Artisan::call('clean:generate', [
            'model'   => 'Blog/Post',
            '--table' => 'posts',
            '--api'   => true,
        ]);

        $controllerPath = __DIR__ . '/../temp/app/Application/Http/Controllers/Api/Blog/ApiPostController.php';
        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        // JSON responses
        $this->assertStringContainsString('return response()->json', $content);
        $this->assertStringNotContainsString('<html>', $content);

        // Service calls in GET-ish methods
        $this->assertStringContainsString('$this->service->paginate()', $content);
        $this->assertStringContainsString('$this->service->find($id)',   $content);
    }

    // ── Full scaffold correctness (originally the main feature test) ─────────

    /** @test */
    public function it_can_generate_full_clean_architecture_scaffold_via_web()
    {
        $appPath = __DIR__ . '/../temp/app';

        $exitCode = Artisan::call('clean:generate', [
            'model'   => 'Blog/Post',
            '--table' => 'posts',
            '--web'   => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $this->assertFileExists($appPath . '/Domain/DTOs/Blog/PostData.php');
        $this->assertFileExists($appPath . '/Domain/DTOs/Blog/CreatePostData.php');
        $this->assertFileExists($appPath . '/Domain/DTOs/Blog/UpdatePostData.php');
        $this->assertFileExists($appPath . '/Domain/Contracts/Blog/PostRepositoryInterface.php');
        $this->assertFileExists($appPath . '/Infrastructure/Repositories/Blog/EloquentPostRepository.php');
        $this->assertFileExists($appPath . '/Domain/Services/Blog/PostServiceInterface.php');
        $this->assertFileExists($appPath . '/Domain/Services/Blog/PostService.php');
        $this->assertFileExists($appPath . '/Application/Http/Controllers/Blog/PostController.php');

        $providerContent = file_get_contents($appPath . '/Providers/AppServiceProvider.php');
        $this->assertStringContainsString('App\Domain\Contracts\Blog\PostRepositoryInterface', $providerContent);
        $this->assertStringContainsString('App\Infrastructure\Repositories\Blog\EloquentPostRepository', $providerContent);

        $routeContent = file_get_contents(__DIR__ . '/../temp/routes/web.php');
        $this->assertStringContainsString('App\Application\Http\Controllers\Blog\PostController', $routeContent);

        $createDto = file_get_contents($appPath . '/Domain/DTOs/Blog/CreatePostData.php');
        $this->assertStringContainsString('namespace App\Domain\DTOs\Blog;', $createDto);
        $this->assertStringContainsString('public readonly string $title', $createDto);
    }
}
