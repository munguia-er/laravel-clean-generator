<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Base Namespace
    |--------------------------------------------------------------------------
    |
    | The default root namespace where the generated files should be placed.
    | Typically this is 'App'.
    |
    */
    'base_namespace' => 'App',

    /*
    |--------------------------------------------------------------------------
    | Default Path
    |--------------------------------------------------------------------------
    |
    | The default path to generate into. Typically this maps to the app/ directory.
    |
    */
    'base_path' => app_path(),

    /*
    |--------------------------------------------------------------------------
    | Active Architecture
    |--------------------------------------------------------------------------
    |
    | Choose which architecture to use when generating files.
    | Options: 'simple' | 'clean'
    |
    | simple  — Everything under App/, flat and conventional (recommended for quick projects)
    | clean   — Domain / Application / Infrastructure separation (DDD style - more configuration needed)
    |
    */
    'architecture' => 'simple',

    /*
    |--------------------------------------------------------------------------
    | Default Controller Stack
    |--------------------------------------------------------------------------
    |
    | Determines which controller type to generate when neither --api nor --web
    | is specified on the command line.
    | Options: 'web' | 'api'
    |
    */
    'default_stack' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Architecture Definitions
    |--------------------------------------------------------------------------
    |
    | Defines the directory paths for each artifact type per architecture.
    | Paths are relative to the module root (under base_path/base_namespace).
    |
    */
    'architectures' => [

        'simple' => [
            'models'                => 'Models',
            'dtos'                  => 'DTOs',
            'repository_interfaces' => 'Interfaces/Repositories',
            'service_interfaces'    => 'Interfaces/Services',
            'repositories'          => 'Repositories',
            'services'              => 'Services',
            'controllers'           => 'Http/Controllers',
            'requests'              => 'Http/Requests',
        ],

        'clean' => [
            'models'                => 'Models',
            'dtos'                  => 'Domain/DTOs',
            'repository_interfaces' => 'Domain/Contracts',
            'service_interfaces'    => 'Domain/Services',   // interface lives alongside its implementation
            'repositories'          => 'Infrastructure/Repositories',
            'services'              => 'Domain/Services',
            'controllers'           => 'Application/Http/Controllers',
            'requests'              => 'Application/Http/Requests',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Generator Toggles
    |--------------------------------------------------------------------------
    |
    | Enable or disable the generation of specific components by default.
    |
    */
    'generate' => [
        'dtos'     => true,
        'requests' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Registration
    |--------------------------------------------------------------------------
    |
    | Automatically register bindings in the AppServiceProvider and
    | routes in the appropriate route files via AST modifications.
    |
    */
    'auto_register' => [
        'bindings' => true,
        'routes'   => true,
    ],

];
