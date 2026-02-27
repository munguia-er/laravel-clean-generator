<?php

namespace MunguiaEr\LaravelCleanGenerator\Generators;

use MunguiaEr\LaravelCleanGenerator\Support\PathResolver;

class ServiceGenerator extends AbstractGenerator
{
    public function generate(string $input, string $tableName, bool $force = false): array
    {
        $modelName = class_basename(str_replace('/', '\\', $input));

        // Service interface and implementation may live in different directories
        // (e.g. simple arch: Interfaces/Services vs Services).
        // In clean arch both resolve to Domain/Services — behaviour stays identical.
        $serviceInterfaceResolver = PathResolver::for($input)->forType('service_interfaces');
        $serviceImplResolver      = PathResolver::for($input)->forType('services');

        $interfaceDir = $serviceInterfaceResolver->path();
        $implDir      = $serviceImplResolver->path();

        $this->ensureDirectoryExists($interfaceDir);
        $this->ensureDirectoryExists($implDir);

        $interfaceNamespace = $serviceInterfaceResolver->namespace();
        $implNamespace      = $serviceImplResolver->namespace();

        $interfaceName = "{$modelName}ServiceInterface";
        $implName      = "{$modelName}Service";

        $dtoNamespace           = PathResolver::for($input)->forType('dtos')->namespace();
        $repoInterfaceNamespace = PathResolver::for($input)->forType('repository_interfaces')->namespace();

        // 1. Interface
        $interfacePath = $interfaceDir . '/' . $interfaceName . '.php';

        if (!file_exists($interfacePath) || $force) {
            $stub = $this->getStub('service-interface');
            $stub = str_replace('{{ namespace }}',          $interfaceNamespace,                          $stub);
            $stub = str_replace('{{ class }}',              $interfaceName,                               $stub);
            $stub = str_replace('{{ createDtoInterface }}', $dtoNamespace . '\\Create' . $modelName . 'Data', $stub);
            $stub = str_replace('{{ updateDtoInterface }}', $dtoNamespace . '\\Update' . $modelName . 'Data', $stub);
            $stub = str_replace('{{ createDtoClass }}',     'Create' . $modelName . 'Data',               $stub);
            $stub = str_replace('{{ updateDtoClass }}',     'Update' . $modelName . 'Data',               $stub);

            file_put_contents($interfacePath, $stub);
        }

        // 2. Implementation
        $implPath = $implDir . '/' . $implName . '.php';

        if (!file_exists($implPath) || $force) {
            $stub = $this->getStub('service-impl');
            $stub = str_replace('{{ namespace }}',                $implNamespace,                                              $stub);
            $stub = str_replace('{{ class }}',                    $implName,                                                   $stub);
            $stub = str_replace('{{ interfaceName }}',            $interfaceNamespace . '\\' . $interfaceName,                $stub);
            $stub = str_replace('{{ interfaceClass }}',           $interfaceName,                                              $stub);
            $stub = str_replace('{{ repositoryInterfaceName }}',  $repoInterfaceNamespace . '\\' . $modelName . 'RepositoryInterface', $stub);
            $stub = str_replace('{{ repositoryInterfaceClass }}', $modelName . 'RepositoryInterface',                         $stub);
            $stub = str_replace('{{ createDtoInterface }}',       $dtoNamespace . '\\Create' . $modelName . 'Data',           $stub);
            $stub = str_replace('{{ updateDtoInterface }}',       $dtoNamespace . '\\Update' . $modelName . 'Data',           $stub);
            $stub = str_replace('{{ createDtoClass }}',           'Create' . $modelName . 'Data',                             $stub);
            $stub = str_replace('{{ updateDtoClass }}',           'Update' . $modelName . 'Data',                             $stub);

            file_put_contents($implPath, $stub);
        }

        return [
            'interface'      => $interfaceNamespace . '\\' . $interfaceName,
            'implementation' => $implNamespace . '\\' . $implName,
        ];
    }
}
