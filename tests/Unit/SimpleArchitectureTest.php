<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;
use MunguiaEr\LaravelCleanGenerator\Generators\DtoGenerator;
use MunguiaEr\LaravelCleanGenerator\Generators\RepositoryGenerator;
use MunguiaEr\LaravelCleanGenerator\Generators\ServiceGenerator;
use MunguiaEr\LaravelCleanGenerator\Generators\ControllerGenerator;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;

/**
 * Verifies that the 'simple' architecture generates files in the expected
 * App/-based flat structure (layer-first, module as sub-path).
 *
 * Expected structure for Blog/Post:
 *   App/DTOs/Blog/PostData.php
 *   App/Interfaces/Repositories/Blog/PostRepositoryInterface.php
 *   App/Repositories/Blog/EloquentPostRepository.php
 *   App/Interfaces/Services/Blog/PostServiceInterface.php
 *   App/Services/Blog/PostService.php
 *   App/Http/Controllers/Blog/PostController.php
 */
class SimpleArchitectureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tearDownTempDirectory();
        $this->setupTempDirectory();

        // Switch to simple architecture for all tests in this class
        Config::set('clean-generator.architecture', 'simple');
    }

    protected function tearDown(): void
    {
        $this->tearDownTempDirectory();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_dtos_in_simple_architecture()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new DtoGenerator($introspector);

        $generator->generate('Blog/Post', 'posts');

        // simple arch: dtos = 'DTOs' → app/DTOs/Blog/
        $base = __DIR__ . '/../temp/app/DTOs/Blog';

        $this->assertFileExists($base . '/PostData.php');
        $this->assertFileExists($base . '/CreatePostData.php');
        $this->assertFileExists($base . '/UpdatePostData.php');

        $content = file_get_contents($base . '/PostData.php');
        $this->assertStringContainsString('namespace App\DTOs\Blog;', $content);
        $this->assertStringContainsString('class PostData', $content);
    }

    /** @test */
    public function it_generates_repositories_in_simple_architecture()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new RepositoryGenerator($introspector);

        $generator->generate('Blog/Post', 'posts');

        // simple arch:
        //   repository_interfaces = 'Interfaces/Repositories' → app/Interfaces/Repositories/Blog/
        //   repositories          = 'Repositories'           → app/Repositories/Blog/
        $interfacePath = __DIR__ . '/../temp/app/Interfaces/Repositories/Blog/PostRepositoryInterface.php';
        $implPath      = __DIR__ . '/../temp/app/Repositories/Blog/EloquentPostRepository.php';

        $this->assertFileExists($interfacePath);
        $this->assertFileExists($implPath);

        $interfaceContent = file_get_contents($interfacePath);
        $this->assertStringContainsString('namespace App\Interfaces\Repositories\Blog;', $interfaceContent);
        $this->assertStringContainsString('interface PostRepositoryInterface', $interfaceContent);

        $implContent = file_get_contents($implPath);
        $this->assertStringContainsString('namespace App\Repositories\Blog;', $implContent);
        $this->assertStringContainsString('class EloquentPostRepository implements PostRepositoryInterface', $implContent);
    }

    /** @test */
    public function it_generates_services_in_simple_architecture()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new ServiceGenerator($introspector);

        $generator->generate('Blog/Post', 'posts');

        // simple arch:
        //   service_interfaces = 'Interfaces/Services' → app/Interfaces/Services/Blog/
        //   services           = 'Services'            → app/Services/Blog/
        $interfacePath = __DIR__ . '/../temp/app/Interfaces/Services/Blog/PostServiceInterface.php';
        $implPath      = __DIR__ . '/../temp/app/Services/Blog/PostService.php';

        $this->assertFileExists($interfacePath);
        $this->assertFileExists($implPath);

        $interfaceContent = file_get_contents($interfacePath);
        $this->assertStringContainsString('namespace App\Interfaces\Services\Blog;', $interfaceContent);
        $this->assertStringContainsString('interface PostServiceInterface', $interfaceContent);

        $implContent = file_get_contents($implPath);
        $this->assertStringContainsString('namespace App\Services\Blog;', $implContent);
        $this->assertStringContainsString('class PostService implements PostServiceInterface', $implContent);
    }

    /** @test */
    public function it_generates_api_controller_in_simple_architecture()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new ControllerGenerator($introspector);

        $generator->generateController('Blog/Post', true);

        // simple arch: controllers = 'Http/Controllers' → app/Http/Controllers/Blog/
        $controllerPath = __DIR__ . '/../temp/app/Http/Controllers/Blog/ApiPostController.php';

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('namespace App\Http\Controllers\Blog;', $content);
        $this->assertStringContainsString('class ApiPostController extends Controller', $content);
        // Service interface should come from Interfaces/Services namespace
        $this->assertStringContainsString('App\Interfaces\Services\Blog\PostServiceInterface', $content);
    }

    /** @test */
    public function it_generates_web_controller_in_simple_architecture()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new ControllerGenerator($introspector);

        $generator->generateController('Blog/Post', false);

        // Web controllers — no Api prefix
        $controllerPath = __DIR__ . '/../temp/app/Http/Controllers/Blog/PostController.php';

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('namespace App\Http\Controllers\Blog;', $content);
        $this->assertStringContainsString('class PostController extends Controller', $content);

        // Web controller returns hardcoded HTML — NOT JSON
        $this->assertStringContainsString('<h1>index method called</h1>', $content);
        $this->assertStringNotContainsString('response()->json', $content);
    }
}
