<?php

namespace MunguiaEr\LaravelCleanGenerator\Generators;

use Illuminate\Support\Str;
use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;

abstract class AbstractGenerator
{
    protected string $basePath;
    protected string $baseNamespace;

    public function __construct(
        protected SchemaIntrospector $introspector
    ) {
        $this->basePath = config('clean-generator.base_path', app_path());
        $this->baseNamespace = config('clean-generator.base_namespace', 'App');
    }

    /**
     * Get stub contents
     */
    protected function getStub(string $name): string
    {
        $stubPath = __DIR__ . '/../../Stubs/' . $name . '.stub';
        return file_get_contents($stubPath);
    }

    /**
     * Make sure directory exists
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Abstract generation method to be implemented by children
     * returning the relevant generated class paths or void.
     */
    abstract public function generate(string $input, string $tableName, bool $force = false): mixed;
}
