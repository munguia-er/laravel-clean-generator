<?php

namespace MunguiaEr\LaravelCleanGenerator\Generators;

use MunguiaEr\LaravelCleanGenerator\Support\PathResolver;

class RepositoryGenerator extends AbstractGenerator
{
    public function generate(string $input, string $tableName, bool $force = false): array
    {
        $modelName = class_basename(str_replace('/', '\\', $input));

        $contractResolver = PathResolver::for($input)->forType('repository_interfaces');
        $repoResolver     = PathResolver::for($input)->forType('repositories');

        $domainDir = $contractResolver->path();
        $infraDir  = $repoResolver->path();

        $this->ensureDirectoryExists($domainDir);
        $this->ensureDirectoryExists($infraDir);

        $contractNamespace = $contractResolver->namespace();
        $infraNamespace    = $repoResolver->namespace();

        // Resolve model FQCN dynamically — never hardcoded
        $modelFqcn = PathResolver::for($input)->forType('models')->namespace() . '\\' . $modelName;

        // 1. Generate Interface
        $interfaceName = "{$modelName}RepositoryInterface";
        $interfacePath = $domainDir . '/' . $interfaceName . '.php';

        if (!file_exists($interfacePath) || $force) {
            $interfaceStub = $this->getStub('repository-interface');
            $interfaceStub = str_replace('{{ namespace }}',          $contractNamespace,  $interfaceStub);
            $interfaceStub = str_replace('{{ class }}',              $interfaceName,       $interfaceStub);
            $interfaceStub = str_replace('{{ modelInterfaceName }}', $modelFqcn,           $interfaceStub);
            file_put_contents($interfacePath, $interfaceStub);
        }

        // 2. Generate Eloquent Implementation
        $implName = "Eloquent{$modelName}Repository";
        $implPath = $infraDir . '/' . $implName . '.php';

        if (!file_exists($implPath) || $force) {
            $implStub = $this->getStub('repository-eloquent');
            $implStub = str_replace('{{ namespace }}',       $infraNamespace,                                $implStub);
            $implStub = str_replace('{{ class }}',           $implName,                                      $implStub);
            $implStub = str_replace('{{ interfaceName }}',   $contractNamespace . '\\' . $interfaceName,    $implStub);
            $implStub = str_replace('{{ interfaceClass }}',  $interfaceName,                                 $implStub);
            $implStub = str_replace('{{ modelName }}',       $modelFqcn,                                     $implStub);
            $implStub = str_replace('{{ modelClass }}',      $modelName,                                     $implStub);
            file_put_contents($implPath, $implStub);
        }

        return [
            'interface'      => $contractNamespace . '\\' . $interfaceName,
            'implementation' => $infraNamespace . '\\' . $implName,
        ];
    }
}
