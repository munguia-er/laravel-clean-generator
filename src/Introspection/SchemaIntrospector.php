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

    /**
     * Get complete table information
     */
    public function getTableInfo(string $tableName): array
    {
        if (!Schema::hasTable($tableName)) {
            throw new Exception("Table [{$tableName}] does not exist in the database.");
        }

        $schemaBuilder = $this->connection->getSchemaBuilder();

        return [
            'name'           => $tableName,
            'primary_key'    => $this->getPrimaryKey($tableName, $schemaBuilder),
            'columns'        => $this->getColumns($tableName, $schemaBuilder),
            'has_soft_deletes' => Schema::hasColumn($tableName, 'deleted_at'),
            'has_timestamps' => Schema::hasColumn($tableName, 'created_at') && Schema::hasColumn($tableName, 'updated_at'),
        ];
    }

    /**
     * Extract primary key column
     */
    protected function getPrimaryKey(string $tableName, $schemaBuilder): string
    {
        // Fallback to convention if schema introspection is incomplete
        if (method_exists($schemaBuilder, 'getIndexes')) {
            $indexes = $schemaBuilder->getIndexes($tableName);
            foreach ($indexes as $index) {
                if ($index['primary'] ?? false) {
                    return $index['columns'][0] ?? 'id';
                }
            }
        }
        
        return 'id'; // Default Laravel convention
    }

    /**
     * Get detailed column definitions mapped to PHP types
     */
    protected function getColumns(string $tableName, $schemaBuilder): array
    {
        $columns = [];

        // Laravel 11 offers Schema::getColumns()
        if (method_exists($schemaBuilder, 'getColumns')) {
            $schemaColumns = $schemaBuilder->getColumns($tableName);
            
            foreach ($schemaColumns as $column) {
                $name = $column['name'];
                
                if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                    continue;
                }

                $columns[$name] = [
                    'name'     => $name,
                    'type'     => $this->mapLaravelTypeToPhp($column['type_name'], $name),
                    'nullable' => $column['nullable'] ?? true, // Safe default
                    'default'  => $column['default'] ?? null,
                    'length'   => null, // Not strictly needed for DTOs right now
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
