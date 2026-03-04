<?php

namespace MunguiaEr\LaravelCleanGenerator\Introspection;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Connection;
use Exception;

class SchemaIntrospector
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getTableInfo(string $tableName): array
    {
        if (!Schema::hasTable($tableName)) {
            throw new Exception("Table [{$tableName}] does not exist in the database.");
        }

        $schemaBuilder = $this->connection->getSchemaBuilder();
        $schemaColumns = method_exists($schemaBuilder, 'getColumns') ? $schemaBuilder->getColumns($tableName) : [];
        $indexes = method_exists($schemaBuilder, 'getIndexes') ? $schemaBuilder->getIndexes($tableName) : [];
        $foreignKeys = method_exists($schemaBuilder, 'getForeignKeys') ? $schemaBuilder->getForeignKeys($tableName) : [];

        $pkName = 'id';
        foreach ($indexes as $index) {
            if ($index['primary'] ?? false) {
                $pkName = $index['columns'][0] ?? 'id';
                break;
            }
        }

        $pkMeta = [
            'name' => $pkName,
            'type' => 'int',
            'auto_increment' => true,
        ];

        foreach ($schemaColumns as $col) {
            if ($col['name'] === $pkName) {
                // Determine if integer type
                $isInteger = str_contains(strtolower($col['type_name']), 'int');
                $pkMeta['type'] = $isInteger ? 'int' : 'string';
                $pkMeta['auto_increment'] = $col['auto_increment'] ?? false;
                break;
            }
        }

        // Map Foreign Keys
        $fkMap = [];
        foreach ($foreignKeys as $fk) {
            if (count($fk['columns']) === 1) {
                $fkMap[$fk['columns'][0]] = [
                    'table' => $fk['foreign_table'],
                    'column' => $fk['foreign_columns'][0],
                ];
            }
        }
        
        // Map Unique Constraints
        $uniqueMap = [];
        foreach ($indexes as $idx) {
            if (($idx['unique'] ?? false) && !($idx['primary'] ?? false) && count($idx['columns']) === 1) {
                $uniqueMap[$idx['columns'][0]] = true;
            }
        }

        return [
            'name'           => $tableName,
            'primary_key'    => $pkMeta,
            'columns'        => $this->getColumns($tableName, $schemaBuilder, $pkName, $fkMap, $uniqueMap),
            'has_soft_deletes' => Schema::hasColumn($tableName, 'deleted_at'),
            'has_timestamps' => Schema::hasColumn($tableName, 'created_at') && Schema::hasColumn($tableName, 'updated_at'),
        ];
    }

    /**
     * Get detailed column definitions mapped to PHP types
     */
    protected function getColumns(string $tableName, $schemaBuilder, string $pkName = 'id', array $fkMap = [], array $uniqueMap = []): array
    {
        $columns = [];

        // Laravel 11 offers Schema::getColumns()
        if (method_exists($schemaBuilder, 'getColumns')) {
            $schemaColumns = $schemaBuilder->getColumns($tableName);
            
            foreach ($schemaColumns as $column) {
                $name = $column['name'];
                
                if (in_array($name, [$pkName, 'created_at', 'updated_at', 'deleted_at'])) {
                    continue; // Skip primary key and timestamps
                }

                $fullType = strtolower($column['type'] ?? $column['type_name']);
                $length = null;
                $enumValues = [];
                
                if (preg_match('/char\((\d+)\)/', $fullType, $matches)) {
                    $length = (int) $matches[1];
                } elseif (preg_match('/enum\((.*)\)/', $fullType, $matches)) {
                    $enumValues = array_map(fn($v) => trim($v, "'\" "), explode(',', $matches[1]));
                }

                $columns[$name] = [
                    'name'         => $name,
                    'type'         => $this->mapLaravelTypeToPhp($column['type_name'], $name),
                    'laravel_type' => strtolower($column['type_name']),
                    'nullable'     => $column['nullable'] ?? true, // Safe default
                    'default'      => $column['default'] ?? null,
                    'auto_increment' => $column['auto_increment'] ?? false,
                    'length'       => $length,
                    'enum'         => $enumValues,
                    'foreign_key'  => $fkMap[$name] ?? null,
                    'unique'       => $uniqueMap[$name] ?? false,
                ];
            }
        } else {
            throw new Exception('SchemaBuilder::getColumns not supported on this Laravel version.');
        }

        return $columns;
    }

    /**
     * Map a Laravel internal type to a native PHP type for DTOs
     */
    protected function mapLaravelTypeToPhp(string $typeName, string $columnName = ''): string
    {
        $typeName = strtolower($typeName);

        // SQLite returns 'integer' for booleans sometimes, Laravel conventionally uses is_ or has_ for booleans
        if (str_starts_with($columnName, 'is_') || str_starts_with($columnName, 'has_')) {
            return 'bool';
        }

        if (str_contains($typeName, 'int')) {
            return 'int';
        }
        
        if (str_contains($typeName, 'decimal') || str_contains($typeName, 'float') || str_contains($typeName, 'double') || str_contains($typeName, 'numeric')) {
            return 'float';
        }

        if (str_contains($typeName, 'bool') || str_contains($typeName, 'tinyint(1)')) {
            return 'bool';
        }

        if (str_contains($typeName, 'date') || str_contains($typeName, 'time')) {
            return '\Carbon\Carbon';
        }

        if (str_contains($typeName, 'json') || str_contains($typeName, 'array')) {
            return 'array';
        }

        return 'string';
    }
}
