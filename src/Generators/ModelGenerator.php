<?php

namespace MunguiaEr\LaravelCleanGenerator\Generators;

use Illuminate\Support\Str;
use MunguiaEr\LaravelCleanGenerator\Support\PathResolver;

class ModelGenerator extends AbstractGenerator
{
    public function generate(string $input, string $tableName, bool $force = false): string
    {
        $modelName = class_basename(str_replace('/', '\\', $input));

        $resolver = PathResolver::for($input)->forType('models');
        $modelDir = $resolver->path();
        $this->ensureDirectoryExists($modelDir);

        $modelNamespace = $resolver->namespace();
        $modelPath = $resolver->file($modelName . '.php');

        if (!file_exists($modelPath) || $force) {
            $tableInfo = $this->introspector->getTableInfo($tableName);

            $fillable = $this->buildFillableArray($tableInfo['columns']);
            $castsBlock = $this->buildCastsBlock($tableInfo['columns']);

            $softDeletesImport = '';
            $additionalTraits = '';

            if ($tableInfo['has_soft_deletes']) {
                $softDeletesImport = "use Illuminate\Database\Eloquent\SoftDeletes;\n";
                $additionalTraits = "    use SoftDeletes;\n";
            }

            $stub = $this->getStub('model');
            $stub = str_replace('{{ namespace }}', $modelNamespace, $stub);
            $stub = str_replace('{{ class }}', $modelName, $stub);
            $stub = str_replace('{{ softDeletesImport }}', $softDeletesImport, $stub);
            $stub = str_replace('{{ additionalTraits }}', $additionalTraits, $stub);
            $stub = str_replace('{{ table }}', $tableName, $stub);
            $stub = str_replace('{{ fillable }}', $fillable, $stub);
            $stub = str_replace('{{ casts }}', $castsBlock, $stub);

            file_put_contents($modelPath, $stub);
        }

        return $modelNamespace . '\\' . $modelName;
    }

    protected function buildFillableArray(array $columns): string
    {
        $fillable = [];
        foreach ($columns as $column) {
            // Usually we don't put AI primary keys or timestamps in fillable directly
            if ($column['name'] === 'id' || in_array($column['name'], ['created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            $fillable[] = "'" . $column['name'] . "'";
        }

        return implode(",\n        ", $fillable);
    }

    protected function buildCastsBlock(array $columns): string
    {
        $casts = [];
        foreach ($columns as $column) {
            if (in_array($column['name'], ['created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            if ($column['type'] === 'bool') {
                $casts[] = "'" . $column['name'] . "' => 'boolean'";
            } elseif ($column['type'] === 'int') {
                 $casts[] = "'" . $column['name'] . "' => 'integer'";
            } elseif ($column['type'] === 'float') {
                 $casts[] = "'" . $column['name'] . "' => 'float'";
            } elseif ($column['type'] === 'array') {
                 $casts[] = "'" . $column['name'] . "' => 'array'";
            }
        }

        if (empty($casts)) {
            return '';
        }

        $castsString = implode(",\n        ", $casts);
        
        return "protected \$casts = [\n        {$castsString}\n    ];";
    }
}
