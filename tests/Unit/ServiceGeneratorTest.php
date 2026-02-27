<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;
use MunguiaEr\LaravelCleanGenerator\Generators\ServiceGenerator;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Database\Connection;

class ServiceGeneratorTest extends TestCase
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
    public function it_generates_service_interface_and_implementation()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new ServiceGenerator($introspector);

        $generator->generate('Blog/Post', 'posts');

        // clean arch:
        //   service_interfaces = Domain/Services → app/Domain/Services/Blog/
        //   services           = Domain/Services → app/Domain/Services/Blog/
        $interfacePath = __DIR__ . '/../temp/app/Domain/Services/Blog/PostServiceInterface.php';
        $implPath      = __DIR__ . '/../temp/app/Domain/Services/Blog/PostService.php';

        $this->assertFileExists($interfacePath);
        $this->assertFileExists($implPath);

        $interfaceContent = file_get_contents($interfacePath);
        $this->assertStringContainsString('namespace App\Domain\Services\Blog;', $interfaceContent);
        $this->assertStringContainsString('interface PostServiceInterface', $interfaceContent);
        $this->assertStringContainsString('public function create(CreatePostData $data);', $interfaceContent);

        $implContent = file_get_contents($implPath);
        $this->assertStringContainsString('namespace App\Domain\Services\Blog;', $implContent);
        $this->assertStringContainsString('class PostService implements PostServiceInterface', $implContent);
        $this->assertStringContainsString('public function __construct(', $implContent);
        $this->assertStringContainsString('protected PostRepositoryInterface $repository', $implContent);
    }
}
