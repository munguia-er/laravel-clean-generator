<?php

namespace MunguiaEr\LaravelCleanGenerator\Generators;

use Illuminate\Support\Str;

class DtoGenerator extends AbstractGenerator
{
    public function generate(string $input, string $tableName, bool $force = false): mixed
    {
        $resolver = \MunguiaEr\LaravelCleanGenerator\Support\PathResolver::for($input)->forType('dtos');
        $modelName = class_basename(str_replace('/', '\\', $input));

        $dtoNamespace = $resolver->namespace();
        $dtoDir = $resolver->path();

        $this->ensureDirectoryExists($dtoDir);

        $tableInfo = $this->introspector->getTableInfo($tableName);

        $this->generateFullData($dtoNamespace, $dtoDir, $tableInfo, $modelName, $force);
        $this->generateCreateData($dtoNamespace, $dtoDir, $tableInfo, $modelName, $force);
        $this->generateUpdateData($dtoNamespace, $dtoDir, $tableInfo, $modelName, $force);
        
        return null;
    }

    protected function generateFullData(string $namespace, string $dir, array $tableInfo, string $modelName, bool $force)
    {
        $className = "{$modelName}Data";
        $filePath = $dir . '/' . $className . '.php';

        if (file_exists($filePath) && !$force) {
            return;
        }

        $properties = $this->buildPropertiesString($tableInfo['columns'], true, $tableInfo['primary_key']);

        $stub = $this->getStub('dto');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $className, $stub);
        $stub = str_replace('{{ properties }}', $properties, $stub);

        file_put_contents($filePath, $stub);
    }

    protected function generateCreateData(string $namespace, string $dir, array $tableInfo, string $modelName, bool $force)
    {
        $className = "Create{$modelName}Data";
        $filePath = $dir . '/' . $className . '.php';

        if (file_exists($filePath) && !$force) {
            return;
        }

        $properties = $this->buildPropertiesString($tableInfo['columns']);

        $stub = $this->getStub('dto');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $className, $stub);
        $stub = str_replace('{{ properties }}', $properties, $stub);

        file_put_contents($filePath, $stub);
    }

    protected function generateUpdateData(string $namespace, string $dir, array $tableInfo, string $modelName, bool $force)
    {
        $className = "Update{$modelName}Data";
        $filePath = $dir . '/' . $className . '.php';

        if (file_exists($filePath) && !$force) {
            return;
        }

        // Make everything optional for an update DTO
        $properties = $this->buildPropertiesString($tableInfo['columns'], false, null, true);

        $stub = $this->getStub('dto');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $className, $stub);
        $stub = str_replace('{{ properties }}', $properties, $stub);

        file_put_contents($filePath, $stub);
    }

    protected function buildPropertiesString(array $columns, bool $includeId = false, ?string $primaryKey = null, bool $makeOptional = false): string
    {
        $lines = [];

        if ($includeId && $primaryKey) {
            $lines[] = "        public readonly int|string \${$primaryKey},";
        }

        foreach ($columns as $column) {
            $type = $column['type'];
            $name = $column['name'];
            $isNullable = $makeOptional || $column['nullable'];
            
            $typePrefix = $isNullable ? '?' : '';
            
            $lines[] = "        public readonly {$typePrefix}{$type} \${$name},";
        }

        return implode("\n", $lines);
    }
}
