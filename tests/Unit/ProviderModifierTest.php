<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\AST\ProviderModifier;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use MunguiaEr\LaravelCleanGenerator\Generators\RepositoryGenerator;
use Illuminate\Support\Facades\File;

class ProviderModifierTest extends TestCase
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
    public function it_injects_repository_bindings_into_service_provider()
    {
        $modifier = new ProviderModifier();
        $providerPath = __DIR__ . '/../temp/app/Providers/AppServiceProvider.php';

        $interfaceClass = 'App\\Blog\\Post\\Domain\\Contracts\\PostRepositoryInterface';
        $implementationClass = 'App\\Blog\\Post\\Infrastructure\\EloquentPostRepository';

        $modifier->addBinding($providerPath, $interfaceClass, $implementationClass);

        $content = file_get_contents($providerPath);

        // Verify imports were injected
        $this->assertStringContainsString('use ' . $interfaceClass . ';', $content);
        $this->assertStringContainsString('use ' . $implementationClass . ';', $content);

        // Verify bind statements were injected
        $this->assertStringContainsString('$this->app->bind(PostRepositoryInterface::class, EloquentPostRepository::class);', $content);
        
        // Ensure idempotency
        $modifier->addBinding($providerPath, $interfaceClass, $implementationClass);
        $contentAfterSecondRun = file_get_contents($providerPath);
        
        // Assert we don't have duplicated binds or imports
        $this->assertEquals(1, substr_count($contentAfterSecondRun, 'use ' . $interfaceClass . ';'));
        $this->assertEquals(1, substr_count($contentAfterSecondRun, '$this->app->bind(PostRepositoryInterface::class, EloquentPostRepository::class);'));
    }
}
