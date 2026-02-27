<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;
use MunguiaEr\LaravelCleanGenerator\Generators\ModelGenerator;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Database\Connection;

class ModelGeneratorTest extends TestCase
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
    public function it_generates_an_eloquent_model()
    {
        $connection   = app(Connection::class);
        $introspector = new SchemaIntrospector($connection);
        $generator    = new ModelGenerator($introspector);

        $generator->generate('Blog/Post', 'posts');

        // both architectures: models = 'Models' → app/Models/Blog/Post.php
        $modelPath = __DIR__ . '/../temp/app/Models/Blog/Post.php';

        $this->assertFileExists($modelPath);

        $content = file_get_contents($modelPath);
        $this->assertStringContainsString('namespace App\Models\Blog;', $content);
        $this->assertStringContainsString('class Post extends Model', $content);
        $this->assertStringContainsString("protected \$table = 'posts';", $content);

        // Assert Fillable (excludes id/timestamps)
        $this->assertStringContainsString("'title',", $content);
        $this->assertStringContainsString("'content',", $content);
        $this->assertStringContainsString("'is_published'", $content);
        $this->assertStringNotContainsString("'id'", $content);
        $this->assertStringNotContainsString("'deleted_at'", $content);

        // Assert SoftDeletes
        $this->assertStringContainsString("use Illuminate\Database\Eloquent\SoftDeletes;", $content);
        $this->assertStringContainsString("use SoftDeletes;", $content);

        // Assert Casts
        $this->assertStringContainsString("'is_published' => 'boolean'", $content);
    }
}
