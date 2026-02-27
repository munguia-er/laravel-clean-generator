# Contributing to laravel-clean-generator

Thank you for considering a contribution! This document explains how to get the project running locally and how to submit improvements.

---

## Code of Conduct

This project adheres to the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating you are expected to uphold it.

---

## How to Contribute

### Reporting Bugs

Open a [GitHub Issue](https://github.com/munguia-er/laravel-clean-generator/issues) using the **Bug Report** template. Include:

- PHP and Laravel version
- Exact command you ran
- Expected vs actual output
- Config file (if customised)

### Suggesting Features

Open an issue with the **Feature Request** label. Describe the use case, the proposed command/config change, and any alternative approaches you considered.

### Submitting Pull Requests

1. Fork the repository
2. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/my-improvement
   ```
3. Make your changes
4. Add or update tests to cover your change
5. Ensure all tests pass (see [Testing](#testing))
6. Commit following [Conventional Commits](https://www.conventionalcommits.org/):
   ```
   feat: add --dry-run flag to preview generated files
   fix: prevent duplicate use import in RouteModifier
   ```
7. Push and open a Pull Request against `main`

---

## Development Setup

### Prerequisites

- PHP 8.1+
- Composer
- Git

### Clone & Install

```bash
git clone https://github.com/munguia-er/laravel-clean-generator.git
cd laravel-clean-generator
composer install
```

### Project Layout

```
laravel-clean-generator/
├── config/
│   └── clean-generator.php        # Package configuration
├── src/
│   ├── Commands/                  # Artisan commands
│   ├── Generators/                # File generators (Model, DTO, Repo, Service, Controller)
│   ├── Support/                   # PathResolver
│   ├── Introspection/             # SchemaIntrospector
│   └── AST/                       # RouteModifier, ProviderModifier (PHP-Parser)
├── Stubs/                         # Blade-style stub templates
├── tests/
│   ├── Feature/                   # End-to-end command tests
│   └── Unit/                      # Generator and resolver unit tests
└── composer.json
```

---

## Testing

The test suite uses [PHPUnit 11](https://phpunit.de) and [Orchestra Testbench](https://github.com/orchestral/testbench) with an in-memory SQLite database.

```bash
# Run the full test suite
composer test

# Or directly
./vendor/bin/phpunit --testdox
```

### Writing Tests

- Unit tests live in `tests/Unit/` and test individual generators / resolvers in isolation.
- Feature tests live in `tests/Feature/` and call the Artisan command end-to-end.
- Use `setupTempDirectory()` / `tearDownTempDirectory()` (provided by `TestCase`) to avoid leftover files between tests.
- Pin `architecture` and `default_stack` via `Config::set(...)` when your test requires a specific mode.

### Test Coverage Requirements

Every new feature or bug fix **must** include:

1. At least one test that verifies the new behaviour
2. One test (or assertion) that verifies the previous behaviour still works (regression)

---

## Coding Standards

- **PSR-12** code style
- **Strict types** (`declare(strict_types=1)`) wherever practical
- No business logic in the `handle()` method — delegate to generators and AST modifiers
- No hardcoded namespace strings — always use `PathResolver`
- Stubs use `{{ placeholder }}` tokens and must be kept in `Stubs/`

---

## Commit Message Format

```
<type>(<scope>): <short description>

[optional body]
[optional footer]
```

Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `perf`

---

## Versioning

This project follows [Semantic Versioning](https://semver.org/). Changes to:

- **Generated file structure** → MAJOR
- **New command option / new generator** → MINOR
- **Bug fix / internal refactor** → PATCH

Always update `CHANGELOG.md` under the `[Unreleased]` section. Maintainers will move entries to a versioned section on release.
