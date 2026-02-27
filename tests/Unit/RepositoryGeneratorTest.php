<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;
use MunguiaEr\LaravelCleanGenerator\Generators\RepositoryGenerator;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Database\Connection;

class RepositoryGeneratorTest extends TestCase
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
    public function it_generates_repository_interface_and_implementation()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new RepositoryGenerator($introspector);

        $generator->generate('Blog/Post', 'posts');

        // clean arch:
        //   repository_interfaces = Domain/Contracts → app/Domain/Contracts/Blog/
        //   repositories          = Infrastructure/Repositories → app/Infrastructure/Repositories/Blog/
        $interfacePath = __DIR__ . '/../temp/app/Domain/Contracts/Blog/PostRepositoryInterface.php';
        $implPath      = __DIR__ . '/../temp/app/Infrastructure/Repositories/Blog/EloquentPostRepository.php';

        $this->assertFileExists($interfacePath);
        $this->assertFileExists($implPath);

        $interfaceContent = file_get_contents($interfacePath);
        $this->assertStringContainsString('namespace App\Domain\Contracts\Blog;', $interfaceContent);
        $this->assertStringContainsString('interface PostRepositoryInterface', $interfaceContent);
        $this->assertStringContainsString('public function find($id);', $interfaceContent);

        $implContent = file_get_contents($implPath);
        $this->assertStringContainsString('namespace App\Infrastructure\Repositories\Blog;', $implContent);
        $this->assertStringContainsString('class EloquentPostRepository implements PostRepositoryInterface', $implContent);
        $this->assertStringContainsString('public function __construct(', $implContent);
        $this->assertStringContainsString('protected Post $model', $implContent);
    }
}
