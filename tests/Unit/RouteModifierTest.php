<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\AST\RouteModifier;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Support\Facades\File;

class RouteModifierTest extends TestCase
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
    public function it_injects_api_resource_route_and_use_statements()
    {
        $modifier = new RouteModifier();
        $routePath = __DIR__ . '/../temp/routes/api.php';

        $controllerClass = 'App\\Blog\\Post\\Application\\Http\\Controllers\\ApiPostController';
        $routeName = 'posts';

        $modifier->addResourceRoute($routePath, $controllerClass, $routeName, true);

        $content = file_get_contents($routePath);

        $this->assertStringContainsString('use ' . $controllerClass . ';', $content);
        $this->assertStringContainsString("Route::apiResource('{$routeName}', ApiPostController::class);", $content);

        // Ensure idempotency
        $modifier->addResourceRoute($routePath, $controllerClass, $routeName, true);
        $contentAfterSecondRun = file_get_contents($routePath);
        
        $this->assertEquals(1, substr_count($contentAfterSecondRun, 'use ' . $controllerClass . ';'));
        $this->assertEquals(1, substr_count($contentAfterSecondRun, "Route::apiResource('{$routeName}', ApiPostController::class);"));
    }

    /** @test */
    public function it_injects_web_resource_route()
    {
        $modifier = new RouteModifier();
        
        // Web context setup
        $webRoutePath = __DIR__ . '/../temp/routes/web.php';
        file_put_contents($webRoutePath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n");
        
        $controllerClass = 'App\\Blog\\Post\\Application\\Http\\Controllers\\PostController';
        $routeName = 'posts';

        $modifier->addResourceRoute($webRoutePath, $controllerClass, $routeName, false);

        $content = file_get_contents($webRoutePath);

        $this->assertStringContainsString('use ' . $controllerClass . ';', $content);
        $this->assertStringContainsString("Route::resource('{$routeName}', PostController::class);", $content);
    }
}
