<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;
use MunguiaEr\LaravelCleanGenerator\Generators\ControllerGenerator;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Database\Connection;

class ControllerGeneratorTest extends TestCase
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

    /** @test */
    public function it_generates_api_controller()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new ControllerGenerator($introspector);

        $generator->generateController('Blog/Post', true);

        // clean arch: controllers = Application/Http/Controllers → app/Application/Http/Controllers/Blog/
        // API controllers get the Api prefix
        $controllerPath = __DIR__ . '/../temp/app/Application/Http/Controllers/Blog/ApiPostController.php';

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('namespace App\Application\Http\Controllers\Blog;', $content);
        $this->assertStringContainsString('class ApiPostController extends Controller', $content);
        $this->assertStringContainsString('public function __construct(', $content);
        $this->assertStringContainsString('protected PostServiceInterface $service', $content);

        // Service interface import must come from Domain\Services\Blog (clean arch)
        $this->assertStringContainsString('App\Domain\Services\Blog\PostServiceInterface', $content);

        // API controller returns JSON
        $this->assertStringContainsString('return response()->json', $content);
        $this->assertStringContainsString('public function index()', $content);
    }

    /** @test */
    public function it_generates_web_controller()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new ControllerGenerator($introspector);

        $generator->generateController('Blog/Post', false);

        // Web controllers have no Api prefix
        $controllerPath = __DIR__ . '/../temp/app/Application/Http/Controllers/Blog/PostController.php';

        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('namespace App\Application\Http\Controllers\Blog;', $content);
        $this->assertStringContainsString('class PostController extends Controller', $content);

        // Web controller returns hardcoded HTML — NOT JSON
        $this->assertStringContainsString('<h1>index method called</h1>', $content);
        $this->assertStringNotContainsString('response()->json', $content);
    }
}
