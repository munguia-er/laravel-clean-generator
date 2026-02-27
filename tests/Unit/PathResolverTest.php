<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests\Unit;

use MunguiaEr\LaravelCleanGenerator\Support\PathResolver;
use MunguiaEr\LaravelCleanGenerator\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class PathResolverTest extends TestCase
{
    /** @test */
    public function it_resolves_namespaces_based_on_config_types()
    {
        // clean arch (default in TestCase): dtos = 'Domain/DTOs'
        // input 'Admin/Blog/Post' → modelName='Post', modulePath='Admin/Blog'
        // namespace = App\Domain\DTOs\Admin\Blog
        $resolver = PathResolver::for('Admin/Blog/Post')->forType('dtos');

        $this->assertEquals('App\Domain\DTOs\Admin\Blog', $resolver->namespace());
    }

    /** @test */
    public function it_resolves_file_paths_based_on_config_types()
    {
        // path = basePath/Domain/DTOs/Admin/Blog
        $resolver = PathResolver::for('Admin/Blog/Post')->forType('dtos');

        $expectedPath = dirname(__DIR__) . '/temp/app/Domain/DTOs/Admin/Blog/CreatePostData.php';

        $this->assertEquals($expectedPath, $resolver->file('CreatePostData.php'));
    }

    /** @test */
    public function it_respects_custom_architecture_config_values()
    {
        Config::set('clean-generator.architectures.clean.dtos', 'Core/DataTransferObjects');
        Config::set('clean-generator.base_namespace', 'Acme');

        // input 'Admin/Inventory/Product' → modelName='Product', modulePath='Admin/Inventory'
        $resolver = PathResolver::for('Admin/Inventory/Product')->forType('dtos');

        $this->assertEquals('Acme\Core\DataTransferObjects\Admin\Inventory', $resolver->namespace());

        $expectedPath = dirname(__DIR__) . '/temp/app/Core/DataTransferObjects/Admin/Inventory/CreateProductData.php';
        $this->assertEquals($expectedPath, $resolver->file('CreateProductData.php'));
    }

    /** @test */
    public function it_handles_empty_type_paths()
    {
        Config::set('clean-generator.architectures.clean.dtos', '');

        // input 'Blog/Post' → modelName='Post', modulePath='Blog'
        // namespace = App\Blog  (no layer segment)
        $resolver = PathResolver::for('Blog/Post')->forType('dtos');

        $this->assertEquals('App\Blog', $resolver->namespace());

        $expectedPath = dirname(__DIR__) . '/temp/app/Blog/CreatePostData.php';
        $this->assertEquals($expectedPath, $resolver->file('CreatePostData.php'));
    }

    /** @test */
    public function it_handles_single_segment_input()
    {
        // input 'Post' → modelName='Post', modulePath=''
        // namespace = App\Domain\DTOs  (no module segment)
        $resolver = PathResolver::for('Post')->forType('dtos');

        $this->assertEquals('App\Domain\DTOs', $resolver->namespace());

        $expectedPath = dirname(__DIR__) . '/temp/app/Domain/DTOs/PostData.php';
        $this->assertEquals($expectedPath, $resolver->file('PostData.php'));
    }

    /** @test */
    public function it_handles_deep_module_path()
    {
        // input 'Blog/Admin/Post' → modelName='Post', modulePath='Blog/Admin'
        $resolver = PathResolver::for('Blog/Admin/Post')->forType('dtos');

        $this->assertEquals('App\Domain\DTOs\Blog\Admin', $resolver->namespace());

        $expectedPath = dirname(__DIR__) . '/temp/app/Domain/DTOs/Blog/Admin/PostData.php';
        $this->assertEquals($expectedPath, $resolver->file('PostData.php'));
    }

    /** @test */
    public function it_resolves_simple_architecture_paths()
    {
        Config::set('clean-generator.architecture', 'simple');

        // input 'Blog/Post' → modelName='Post', modulePath='Blog'
        $dtoResolver  = PathResolver::for('Blog/Post')->forType('dtos');
        $repoResolver = PathResolver::for('Blog/Post')->forType('repository_interfaces');
        $svcResolver  = PathResolver::for('Blog/Post')->forType('service_interfaces');

        $this->assertEquals('App\DTOs\Blog', $dtoResolver->namespace());
        $this->assertEquals('App\Interfaces\Repositories\Blog', $repoResolver->namespace());
        $this->assertEquals('App\Interfaces\Services\Blog', $svcResolver->namespace());
    }

    /** @test */
    public function it_resolves_simple_architecture_single_segment()
    {
        Config::set('clean-generator.architecture', 'simple');

        // input 'Post' → modelName='Post', modulePath=''
        $dtoResolver = PathResolver::for('Post')->forType('dtos');

        $this->assertEquals('App\DTOs', $dtoResolver->namespace());
    }

    /** @test */
    public function it_falls_back_to_legacy_paths_config()
    {
        // Simulate user with old published config (flat paths.* key, no architectures map)
        Config::set('clean-generator.architectures.clean', null);
        Config::set('clean-generator.paths.dtos', 'LegacyDTOs');

        $resolver = PathResolver::for('Blog/Post')->forType('dtos');

        $this->assertEquals('App\LegacyDTOs\Blog', $resolver->namespace());
    }
}
