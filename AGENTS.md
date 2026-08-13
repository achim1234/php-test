# AGENTS.md

## Project Overview

This is a PHP project running locally with **DDEV**.

The project uses:

* PHP **8.3**
* DDEV as the local development environment
* Composer for PHP dependencies
* Git for version control
* PhpStorm as the primary IDE
* Junie as the AI coding agent

Always work within the existing project architecture and conventions. Prefer small, focused changes over broad refactoring.

---

## Development Environment

### DDEV

All PHP, Composer, Symfony/TYPO3 CLI, database, and application commands should be executed through DDEV.

Use:

```bash
ddev start
```

Run PHP:

```bash
ddev exec php -v
```

Run Composer:

```bash
ddev composer install
ddev composer update
```

Run PHP commands:

```bash
ddev exec php <command>
```

Do **not** assume that PHP installed on the host system is the project's PHP version.

The expected PHP version is:

```text
PHP 8.3
```

Before making environment-related assumptions, verify with:

```bash
ddev exec php -v
```

---

## Composer

Use DDEV for all Composer operations:

```bash
ddev composer install
ddev composer require vendor/package
ddev composer remove vendor/package
```

Do not manually edit `composer.lock`.

If dependencies need to change, use Composer so that `composer.json` and `composer.lock` remain consistent.

Do not update unrelated dependencies just to solve an unrelated problem.

---

## PHP Standards

Write modern PHP 8.3-compatible code.

Prefer:

* strict typing
* typed properties
* parameter types
* return types
* constructor property promotion where appropriate
* readonly properties where appropriate
* enums where appropriate
* small, focused classes
* dependency injection
* clear interfaces
* immutable value objects where appropriate

Example:

```php
<?php

declare(strict_types=1);

final class ExampleService
{
    public function __construct(
        private readonly SomeDependency $dependency,
    ) {}

    public function execute(string $value): bool
    {
        return $this->dependency->process($value);
    }
}
```

Avoid:

* unnecessary static methods
* global state
* service locators
* duplicated logic
* overly large methods
* deeply nested conditionals
* premature abstractions
* suppressing errors with `@`
* `mixed` when a more precise type is possible

Do not introduce a new architecture or framework pattern unless the existing project already uses it or the change explicitly requires it.

---

## Existing Code Takes Priority

Before creating or changing code:

1. Inspect nearby existing code.
2. Identify the established pattern.
3. Follow the existing naming and architectural conventions.
4. Reuse existing services, helpers, repositories, DTOs, or utilities where appropriate.
5. Avoid introducing a second solution to an already solved problem.

Consistency with the existing codebase is more important than introducing a theoretically cleaner pattern.

---

## TYPO3 Projects

If this project is a TYPO3 project, follow the conventions of the installed TYPO3 version.

Determine the TYPO3 version from:

```bash
ddev composer show typo3/cms-core
```

Do not assume APIs from another TYPO3 version.

When working with TYPO3:

* Prefer TYPO3's dependency injection.
* Respect TYPO3's extension structure.
* Follow existing Site Configuration conventions.
* Follow existing TypoScript conventions.
* Follow existing Fluid conventions.
* Do not use deprecated APIs when a supported API exists.
* Do not introduce APIs from newer TYPO3 versions than the installed version.
* Check the installed package version before relying on version-specific APIs.

For Extbase code, prefer dependency injection and properly typed repositories/services.

---

## Configuration Files

Before modifying configuration, determine which configuration mechanism the project uses.

Possible configuration files include:

* `composer.json`
* `composer.lock`
* `ext_localconf.php`
* `ext_tables.php`
* `Configuration/`
* `config/`
* `config/sites/`
* `Configuration/TCA/`
* `Configuration/TypoScript/`
* `.ddev/config.yaml`

Do not create duplicate configuration when an existing configuration can be extended.

---

## Database

The database runs inside DDEV.

Do not assume a locally installed MySQL/MariaDB server.

Use DDEV commands to access the database.

For example:

```bash
ddev mysql
```

or:

```bash
ddev exec mysql
```

Do not modify production databases.

Never execute destructive database commands such as:

```sql
DROP DATABASE
DROP TABLE
TRUNCATE
DELETE
```

unless the user explicitly requested the destructive operation.

When writing database queries:

* use parameterized queries
* never concatenate user input into SQL
* avoid unnecessary queries
* preserve existing database abstraction layers

---

## Testing

Before considering a change complete, run the most relevant available checks.

First inspect the project's Composer scripts:

```bash
cat composer.json
```

Look for scripts such as:

* `test`
* `phpunit`
* `phpstan`
* `php-cs-fixer`
* `ecs`
* `lint`

Use the project's existing tools rather than introducing new tooling.

Typical commands may be:

```bash
ddev composer test
```

```bash
ddev exec vendor/bin/phpunit
```

```bash
ddev exec vendor/bin/phpstan analyse
```

Do not assume these commands exist. Check `composer.json` first.

---

## Code Quality

Keep changes minimal and focused.

Do not:

* reformat unrelated files
* rename unrelated classes
* upgrade dependencies without a reason
* refactor unrelated code
* change coding standards across the project
* modify generated files unnecessarily
* remove existing functionality without explicit instruction

If a bug can be fixed with a 10-line change, do not turn it into a 200-line refactoring.

---

## Security

Never introduce:

* hardcoded passwords
* API keys
* access tokens
* private keys
* credentials
* secrets in source control

Use existing environment/configuration mechanisms.

Do not expose sensitive information in:

* logs
* exceptions
* API responses
* HTML
* JavaScript
* database queries

Validate and sanitize external input according to the framework and existing project conventions.

---

## Git

Before modifying files, inspect the current state:

```bash
git status
```

Do not overwrite or discard existing uncommitted user changes.

Do not run destructive commands such as:

```bash
git reset --hard
git clean -fd
git checkout .
```

unless explicitly requested.

Keep commits focused if commits are requested.

Do not create commits automatically unless explicitly asked.

---

## Working With Existing Changes

The working tree may contain changes made by the developer.

Treat existing modifications as intentional.

Before changing a file that already contains uncommitted changes:

1. Inspect the diff.
2. Understand what was changed.
3. Preserve those changes.
4. Modify only what is necessary.

Never silently revert user work.

---

## File Changes

Before creating a new file, check whether an equivalent file already exists.

Before adding a new class, service, helper, or utility, search the project for existing implementations.

Prefer reuse over duplication.

Do not create temporary files inside the project unless necessary.

If temporary files are required, clean them up afterward.

---

## Documentation

Only add documentation when it provides meaningful value.

Prefer documenting:

* non-obvious business rules
* complex algorithms
* architectural decisions
* unusual TYPO3 behavior
* workarounds for external limitations

Do not add comments that merely restate the code.

Bad:

```php
// Get the user
$user = $this->userRepository->findByUid($uid);
```

Better:

```php
// The frontend user must be loaded without the default storage PID
// because users may originate from multiple site roots.
```

---

## Junie Workflow

For every task, follow this workflow:

### 1. Understand

Inspect the relevant files before making changes.

Identify:

* framework/version
* architecture
* existing implementation
* dependencies
* tests
* configuration

### 2. Plan

For non-trivial changes, determine:

* which files need modification
* whether existing functionality can be reused
* what side effects the change may have
* which tests should be run

Do not over-engineer the solution.

### 3. Implement

Make the smallest change that correctly solves the problem.

Follow existing project conventions.

### 4. Verify

Run relevant tests, linters, static analysis, or application checks.

Prefer DDEV commands.

### 5. Review

Check:

```bash
git diff
```

Ensure:

* no unrelated changes were introduced
* no secrets were added
* no debugging code remains
* formatting is consistent
* the implementation matches the request

---

## Command Preference

Prefer these:

```bash
ddev exec ...
ddev composer ...
ddev php ...
```

over running PHP/Composer directly on the host.

Examples:

```bash
ddev exec php -v
ddev composer install
ddev composer test
```

If a project-specific DDEV command already exists, use the project's established command.

---

## Important Rules

1. **Use PHP 8.3.**
2. **Use DDEV for PHP and Composer commands.**
3. **Do not assume the host PHP version.**
4. **Inspect existing code before introducing new patterns.**
5. **Preserve existing uncommitted changes.**
6. **Do not make unrelated refactors.**
7. **Do not upgrade dependencies unless requested or required.**
8. **Do not commit unless explicitly requested.**
9. **Run relevant tests after changes.**
10. **Prefer the smallest correct solution.**
11. **Never introduce secrets or credentials.**
12. **For TYPO3, always respect the installed TYPO3 version.**
