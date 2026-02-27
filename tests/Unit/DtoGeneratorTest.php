<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;
use MunguiaEr\LaravelCleanGenerator\Generators\DtoGenerator;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Database\Connection;

class DtoGeneratorTest extends TestCase
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
    public function it_generates_dto_classes_with_proper_types()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new DtoGenerator($introspector);

        $generator->generate('Blog/Post', 'posts');

        // clean arch: dtos = Domain/DTOs → app/Domain/DTOs/Blog/
        $basePath = __DIR__ . '/../temp/app/Domain/DTOs/Blog';

        $this->assertFileExists($basePath . '/PostData.php');
        $this->assertFileExists($basePath . '/CreatePostData.php');
        $this->assertFileExists($basePath . '/UpdatePostData.php');

        $content = file_get_contents($basePath . '/CreatePostData.php');

        $this->assertStringContainsString('namespace App\Domain\DTOs\Blog;', $content);
        $this->assertStringContainsString('class CreatePostData', $content);
        $this->assertStringContainsString('public readonly string $title', $content);
        $this->assertStringContainsString('public readonly ?string $content', $content);
        // is_published is boolean
        $this->assertStringContainsString('public readonly bool $is_published', $content);
        // id should be skipped in CreateData
        $this->assertStringNotContainsString('$id', $content);
    }
}
