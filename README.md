# laravel-clean-generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/munguia-er/laravel-clean-generator.svg?style=flat-square)](https://packagist.org/packages/munguia-er/laravel-clean-generator)
[![PHP Version](https://img.shields.io/packagist/php-v/munguia-er/laravel-clean-generator?style=flat-square)](https://packagist.org/packages/munguia-er/laravel-clean-generator)
[![License](https://img.shields.io/packagist/l/munguia-er/laravel-clean-generator.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/github/actions/workflow/status/munguia-er/laravel-clean-generator/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/munguia-er/laravel-clean-generator/actions)

A Laravel Artisan package that scaffolds a **Clean Architecture** (or Simple App-based) structure from a single command. It introspects your database schema and generates fully typed **DTOs**, **Repository interfaces & implementations**, **Service interfaces & implementations**, and **Controllers** — organized by architecture and stack (Web or API).

---

## Features

- 🏗 **Dual architecture support** — `clean` (Domain/Application/Infrastructure layers) or `simple` (flat App-based)
- 🌐 **Web & API controllers** — configurable default stack, HTML or JSON responses
- 🗂 **Module sub-paths** — `Blog/Post` generates `App/DTOs/Blog/PostData.php`, not `App/Blog/Post/DTOs`
- 🔐 **Strict validation** — stops early if the model is missing and `--table` is not provided
- 🔄 **Model preservation** — never moves or overwrites an existing model
- 📦 **Auto-binding** — registers Repository → Implementation in `AppServiceProvider`
- 🛣 **Auto-routing** — injects `Route::resource()` or `Route::apiResource()` into the correct route file
- 🧬 **Schema introspection** — detects SoftDeletes, primary keys, nullable columns, casts
- ✅ **Full test coverage** — 33 tests, 162 assertions

---

## Requirements

| Dependency         | Version        |
| ------------------ | -------------- |
| PHP                | `^8.1`         |
| Laravel            | `10.x`, `11.x` |
| `nikic/php-parser` | `^5.0`         |
| `doctrine/dbal`    | `^3.8 \| ^4.0` |

---

## Installation

```bash
composer require munguia-er/laravel-clean-generator
```

The service provider is registered automatically via Laravel's package discovery.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=clean-generator-config
```

---

## Configuration

`config/clean-generator.php`

```php
return [
    /*
     | Active architecture: 'clean' (default) or 'simple'.
     */
    'architecture'    => 'clean',

    /*
     | Default controller stack when neither --api nor --web is passed.
     | Options: 'web' | 'api'
     */
    'default_stack'   => 'web',

    'base_namespace'  => 'App',
    'base_path'       => null, // defaults to app_path()

    /*
     | Auto-register generated bindings and routes, respectively.
     */
    'auto_register'   => [
        'bindings' => true,
        'routes'   => true,
    ],

    /*
     | Directory paths for each artifact type, keyed by architecture.
     */
    'architectures' => [
        'simple' => [
            'models'                 => 'Models',
            'dtos'                   => 'DTOs',
            'repository_interfaces'  => 'Interfaces/Repositories',
            'repositories'           => 'Repositories',
            'service_interfaces'     => 'Interfaces/Services',
            'services'               => 'Services',
            'controllers'            => 'Http/Controllers',
        ],
        'clean' => [
            'models'                 => 'Models',
            'dtos'                   => 'Domain/DTOs',
            'repository_interfaces'  => 'Domain/Contracts',
            'repositories'           => 'Infrastructure/Repositories',
            'service_interfaces'     => 'Domain/Services',
            'services'               => 'Domain/Services',
            'controllers'            => 'Application/Http/Controllers',
        ],
    ],
];
```

---

## Usage

### Basic

```bash
# Infer table from model name (model must exist)
php artisan clean:generate Post

# Specify table explicitly (generates model too)
php artisan clean:generate Post --table=posts

# Module sub-paths
php artisan clean:generate Blog/Post --table=posts
php artisan clean:generate Blog/Admin/Post --table=posts
```

### Web vs API

```bash
# Web controller (HTML hardcoded responses)
php artisan clean:generate Blog/Post --table=posts --web

# API controller (JSON responses) — all files placed under Api/ sub-path
php artisan clean:generate Blog/Post --table=posts --api

# Using both flags is an error
php artisan clean:generate Blog/Post --table=posts --api --web  # → ERROR
```

> **Note:** When `--api` is used, an `Api/` segment is automatically injected into every layer's sub-path. This keeps Web and API scaffolding cleanly separated when both coexist in the same project.

### Options

| Option     | Description                                                           |
| ---------- | --------------------------------------------------------------------- |
| `{model}`  | Model name, optionally with module path (`Blog/Post`)                 |
| `--table=` | Database table to introspect. Required when the model does not exist. |
| `--api`    | Generate an API (JSON) controller under `Api/` sub-path               |
| `--web`    | Generate a Web (HTML) controller                                      |
| `--force`  | Overwrite existing files                                              |

---

## Generated Structure

### Simple architecture — `php artisan clean:generate Blog/Post --table=posts --web`

```
app/
├── Models/Blog/Post.php
├── DTOs/Blog/
│   ├── PostData.php
│   ├── CreatePostData.php
│   └── UpdatePostData.php
├── Interfaces/
│   ├── Repositories/Blog/PostRepositoryInterface.php
│   └── Services/Blog/PostServiceInterface.php
├── Repositories/Blog/EloquentPostRepository.php
├── Services/Blog/PostService.php
└── Http/Controllers/Blog/PostController.php
```

### Clean architecture — `php artisan clean:generate Blog/Post --table=posts --api`

```
app/
├── Models/Api/Blog/Post.php
├── Domain/
│   ├── DTOs/Api/Blog/
│   │   ├── PostData.php
│   │   ├── CreatePostData.php
│   │   └── UpdatePostData.php
│   ├── Contracts/Api/Blog/PostRepositoryInterface.php
│   └── Services/Api/Blog/
│       ├── PostServiceInterface.php
│       └── PostService.php
├── Infrastructure/Repositories/Api/Blog/EloquentPostRepository.php
└── Application/Http/Controllers/Api/Blog/ApiPostController.php
```

---

## Architecture Principles

### Layer responsibility

| Layer                          | Responsibility                                        |
| ------------------------------ | ----------------------------------------------------- |
| **Models**                     | Eloquent ORM models, SoftDeletes, casts               |
| **DTOs**                       | Typed, readonly value objects for data transfer       |
| **Repository Interfaces**      | Persistence contract (domain layer)                   |
| **Repository Implementations** | Eloquent concrete implementations                     |
| **Service Interfaces**         | Business logic contract                               |
| **Service Implementations**    | Orchestrates repository + business rules              |
| **Controllers**                | HTTP entry point; delegates entirely to service layer |

### Model handling behavior

- If `--table` is provided → model is **always generated** (at the Api/-prefixed path when `--api`)
- If `--table` is **not** provided and the model **exists** → model is left untouched; all other layers are generated around it
- If `--table` is **not** provided and the model **does not exist** → command fails immediately with a descriptive error

---

## Versioning

This package follows [Semantic Versioning](https://semver.org/):

- **MAJOR** — breaking changes to the generated file structure or command API
- **MINOR** — new flags, new generators, new architecture options
- **PATCH** — bug fixes, documentation, internal refactors

---

## Testing

```bash
composer test
```

The test suite uses [Orchestra Testbench](https://github.com/orchestral/testbench) with an in-memory SQLite database.

---

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on the development workflow and how to submit pull requests.

---

## Code of Conduct

This project adheres to the [Contributor Covenant](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.

---

## License

MIT — see [LICENSE](LICENSE) for details.
