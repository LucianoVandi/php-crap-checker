# Contributing to php-crap-checker

Thank you for your interest in contributing! This document explains how to get
started and what is expected from pull requests.

## Reporting issues

Before opening a new issue, please search the
[existing issues](https://github.com/LucianoVandi/php-crap-checker/issues) to avoid
duplicates.

When filing a bug report, include:

- php-crap-checker version (or commit SHA)
- PHP version
- Operating system
- The command you ran and the full output
- The relevant section of your Crap4J XML report (if applicable)

For feature requests, describe the problem you are trying to solve rather than
jumping straight to a solution — it helps understand the intent.

## Development setup

PHP is not required on the host machine. All PHP and Composer commands run inside
Docker containers.

```bash
# Clone the repository
git clone https://github.com/LucianoVandi/php-crap-checker.git
cd php-crap-checker

# Install dependencies
make install
```

Available commands:

```bash
make test        # PHPUnit test suite
make stan        # PHPStan (level 9)
make cs-check    # PHP CS Fixer (dry-run)
make cs-fix      # PHP CS Fixer (apply fixes)
make phpmd       # PHPMD
make rector      # Rector (dry-run)
make infection   # Infection mutation testing
make qa          # test + stan (minimum CI gate)
```

To run a single test:

```bash
docker compose run --rm php vendor/bin/phpunit --filter TestName
docker compose run --rm php vendor/bin/phpunit tests/Path/To/SpecificTest.php
```

## Submitting a pull request

1. Fork the repository and create a branch from `main`.
2. Make your changes in small, focused commits.
3. Ensure **all** of the following pass locally before opening the PR:
   - `make test` — no failing tests
   - `make stan` — PHPStan level 9 with no errors (no baseline)
   - `make cs-check` — PSR-12 code style (run `make cs-fix` to auto-fix)
   - `make phpmd` — no PHPMD violations
   - `make rector` — no Rector suggestions
4. Add or update tests for any changed behaviour.
5. Open the pull request against the `main` branch with a clear description of
   what was changed and why.

### PR requirements checklist

- [ ] Tests added/updated and passing
- [ ] PHPStan level 9 passes (no new suppressions)
- [ ] Code style compliant (PSR-12)
- [ ] `declare(strict_types=1)` present in every new PHP file
- [ ] No new `Command::FAILURE` — use `ExitCode` enum values

### Code style

This project follows **PSR-12** enforced by PHP CS Fixer. Run `make cs-fix` to
apply automatic formatting before committing.

## License

By contributing you agree that your changes will be licensed under the
[MIT License](LICENSE) that covers this project.
