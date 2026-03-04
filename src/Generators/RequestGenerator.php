<?php

namespace MunguiaEr\LaravelCleanGenerator\Generators;

use Illuminate\Support\Str;
use MunguiaEr\LaravelCleanGenerator\Support\PathResolver;
use MunguiaEr\LaravelCleanGenerator\Introspection\SchemaIntrospector;

class RequestGenerator extends AbstractGenerator
{
    public function generate(string $input, string $tableName, bool $force = false): array
    {
        $modelName = class_basename(str_replace('/', '\\', $input));

        $resolver = PathResolver::for($input)->forType('requests');
        $requestsDir = $resolver->path();
        $this->ensureDirectoryExists($requestsDir);

        $requestNamespace = $resolver->namespace();

        $storeRequestName = "Store{$modelName}Request";
        $updateRequestName = "Update{$modelName}Request";

        $storeRequestPath = $resolver->file($storeRequestName . '.php');
        $updateRequestPath = $resolver->file($updateRequestName . '.php');

        $tableInfo = $this->introspector->getTableInfo($tableName);

        if (!file_exists($storeRequestPath) || $force) {
            $storeRules = $this->buildRules($tableInfo, 'store');
            $this->generateRequest($storeRequestPath, $requestNamespace, $storeRequestName, $storeRules);
        }

        if (!file_exists($updateRequestPath) || $force) {
            $updateRules = $this->buildRules($tableInfo, 'update', $modelName);
            $this->generateRequest($updateRequestPath, $requestNamespace, $updateRequestName, $updateRules);
        }

        return [
            'store'  => $requestNamespace . '\\' . $storeRequestName,
            'update' => $requestNamespace . '\\' . $updateRequestName,
        ];
    }

    protected function generateRequest(string $path, string $namespace, string $className, string $rules): void
    {
        $stub = $this->getStub('request');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $className, $stub);
        $stub = str_replace('{{ rules }}', $rules, $stub);

        file_put_contents($path, $stub);
    }

    protected function buildRules(array $tableInfo, string $type, string $modelName = ''): string
    {
        $rules = [];
        $pkName = $tableInfo['primary_key']['name'];

        foreach ($tableInfo['columns'] as $colName => $column) {
            if ($colName === $pkName || $column['auto_increment']) {
                continue;
            }

            $colRules = [];

            if ($type === 'store') {
                $colRules[] = $column['nullable'] ? "'nullable'" : "'required'";
            } else {
                $colRules[] = "'sometimes'";
            }

            if ($column['type'] === 'string') {
                $colRules[] = "'string'";
                if ($column['length']) {
                    $colRules[] = "'max:" . $column['length'] . "'";
                }
            } elseif ($column['type'] === 'int') {
                $colRules[] = "'integer'";
            } elseif ($column['type'] === 'float') {
                $colRules[] = "'numeric'";
            } elseif ($column['type'] === 'bool') {
                $colRules[] = "'boolean'";
            } elseif ($column['type'] === '\Carbon\Carbon') {
                $colRules[] = "'date'";
            }

            if (!empty($column['enum'])) {
                $colRules[] = "'in:" . implode(',', $column['enum']) . "'";
            }

            if (!empty($column['foreign_key'])) {
                $colRules[] = "'exists:" . $column['foreign_key']['table'] . "," . $column['foreign_key']['column'] . "'";
            }

            if (!empty($column['unique'])) {
                if ($type === 'store') {
                    $colRules[] = "'unique:" . $tableInfo['name'] . "," . $colName . "'";
                } else {
                    $routeParam = Str::camel($modelName);
                    // Use ignore helper formatting
                    // ['unique:table,column,' . $this->route('model') . ',id']
                    $colRules[] = "Illuminate\Validation\Rule::unique('" . $tableInfo['name'] . "', '" . $colName . "')->ignore(\$this->route('" . $routeParam . "'))";
                }
            }

            $ruleString = "            '" . $colName . "' => [\n                " . implode(",\n                ", $colRules) . "\n            ],";
            $rules[] = $ruleString;
        }

        return implode("\n", $rules);
    }
}
