<?php

namespace MunguiaEr\LaravelCleanGenerator\Support;

class PathResolver
{
    /**
     * The module sub-path (everything before the model name).
     * e.g. input 'Blog/Post' → modulePath = 'Blog'
     * e.g. input 'Blog/Admin/Post' → modulePath = 'Blog/Admin'
     * e.g. input 'Post' → modulePath = ''
     */
    protected string $modulePath;

    /**
     * The model class name (last segment of the input).
     * e.g. input 'Blog/Post' → modelName = 'Post'
     */
    protected string $modelName;

    /**
     * The layer directory path resolved from the active architecture config.
     * e.g. 'DTOs', 'Domain/DTOs', 'Infrastructure/Repositories', etc.
     */
    protected string $type = '';

    protected function __construct(string $moduleInput)
    {
        // Normalise separators → forward slashes, strip leading/trailing slashes
        $normalized = trim(str_replace('\\', '/', $moduleInput), '/');
        $segments   = explode('/', $normalized);

        $this->modelName  = array_pop($segments);
        $this->modulePath = implode('/', $segments); // '' when input has a single segment
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Start building a resolver for a given model input.
     * Examples: 'Post', 'Blog/Post', 'Blog/Admin/Post'
     */
    public static function for(string $moduleInput): self
    {
        return new self($moduleInput);
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * Set the architecture layer type to resolve.
     *
     * Resolution order:
     *  1. architectures.{active_architecture}.{type}
     *  2. paths.{type}  (legacy fallback for old published configs)
     *  3. '' (empty — module path only)
     */
    public function forType(string $type): self
    {
        $architecture = config('clean-generator.architecture', 'clean');

        $this->type = config(
            "clean-generator.architectures.{$architecture}.{$type}",
            // Legacy fallback: old flat paths.* key
            config("clean-generator.paths.{$type}", '')
        );

        return $this;
    }

    // -------------------------------------------------------------------------
    // Outputs
    // -------------------------------------------------------------------------

    /**
     * Build the fully qualified PHP namespace for the artifact.
     *
     * Structure: {baseNamespace}\{layer}\{modulePath}
     *
     * Examples (simple arch, input 'Blog/Post'):
     *   forType('dtos')    → App\DTOs\Blog
     *   forType('models')  → App\Models\Blog
     *
     * Examples (clean arch, input 'Blog/Post'):
     *   forType('dtos')                  → App\Domain\DTOs\Blog
     *   forType('repository_interfaces') → App\Domain\Contracts\Blog
     */
    public function namespace(): string
    {
        $baseNamespace = config('clean-generator.base_namespace', 'App');
        $typeNamespace = str_replace('/', '\\', $this->type);
        $moduleNs      = str_replace('/', '\\', $this->modulePath);

        return implode('\\', array_filter([$baseNamespace, $typeNamespace, $moduleNs]));
    }

    /**
     * Build the absolute filesystem directory path for the artifact.
     *
     * Structure: {basePath}/{layer}/{modulePath}
     *
     * Examples (simple arch, input 'Blog/Post'):
     *   forType('dtos')    → /app/DTOs/Blog
     *   forType('models')  → /app/Models/Blog
     *
     * Examples (clean arch, input 'Blog/Post'):
     *   forType('dtos')         → /app/Domain/DTOs/Blog
     *   forType('repositories') → /app/Infrastructure/Repositories/Blog
     */
    public function path(): string
    {
        $basePath = config('clean-generator.base_path', app_path());

        $suffix = implode('/', array_filter([$this->type, $this->modulePath]));

        return rtrim($basePath, '/') . ($suffix !== '' ? '/' . $suffix : '');
    }

    /**
     * Build the absolute filesystem file path including the filename.
     */
    public function file(string $filename): string
    {
        return $this->path() . '/' . ltrim($filename, '/');
    }

    /**
     * Get the model name (last segment of the input).
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * Get the configured layer path snippet (relative, e.g. 'DTOs' or 'Domain/DTOs').
     */
    public function relativeConfiguredPath(): string
    {
        return $this->type;
    }
}
