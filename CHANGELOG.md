# Changelog

All notable changes to `munguia-er/laravel-clean-generator` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.0.0] — 2026-02-27

### Added

- `clean:generate` Artisan command with `{model}`, `--table`, `--api`, `--web`, and `--force` options
- **Dual architecture support** — `clean` (Domain/Application/Infrastructure layers) and `simple` (flat App-based) configurable via `clean-generator.architecture`
- **Web vs API stack** — `--web` generates hardcoded HTML controllers; `--api` generates JSON controllers and places all artifacts under an `Api/` sub-path within each layer
- **Configurable default stack** — `clean-generator.default_stack` (defaults to `web`); used when neither `--api` nor `--web` is passed
- **Module sub-path support** — `Blog/Post` places artifacts as `App/DTOs/Blog/PostData.php` (layer-first, module as sub-path)
- `PathResolver` — centralised path and namespace resolution per architecture
- `ModelGenerator` — generates Eloquent models with fillable, casts, SoftDeletes, and primary key detection via schema introspection
- `DtoGenerator` — generates `PostData`, `CreatePostData`, `UpdatePostData` with fully typed readonly properties
- `RepositoryGenerator` — generates interface + Eloquent implementation; model reference resolved dynamically
- `ServiceGenerator` — generates interface + implementation; delegates to repository
- `ControllerGenerator` — selects `controller-api.stub` or `controller-web.stub`; all four GET-ish methods invoke the service layer
- `SchemaIntrospector` — introspects the database for columns, types, nullability, SoftDeletes, and primary keys
- `ProviderModifier` — safely injects `$this->app->bind(...)` into `AppServiceProvider` using PHP-Parser AST manipulation
- `RouteModifier` — safely injects `Route::resource()` / `Route::apiResource()` without duplicating existing entries; auto-creates `routes/api.php` when missing
- Strict validation: model-missing guard (exits with descriptive error when model does not exist and `--table` is absent); `--api` + `--web` mutual exclusion guard
- Model preservation: existing model is never moved or overwritten when `--api` is used without `--table`
- `CleanGeneratorServiceProvider` — registers command and publishes config/stubs
- 9 customisable Blade-style stubs under `Stubs/`
- Full test suite — 33 tests, 162 assertions (PHPUnit 11, Orchestra Testbench)

[Unreleased]: https://github.com/munguia-er/laravel-clean-generator/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/munguia-er/laravel-clean-generator/releases/tag/v1.0.0
