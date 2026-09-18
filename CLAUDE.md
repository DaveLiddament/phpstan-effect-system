# PHPStan Effect System

## Docker

This project uses Docker for all PHP operations. There is one service per
supported PHP version: `php84`, `php85` (and `latest`). Default to
`php84` unless a specific version is being checked.

Examples:
- `docker compose run --rm php84 composer install`
- `docker compose run --rm php84 vendor/bin/phpunit`
- `docker compose run --rm php84 vendor/bin/phpstan analyse`

`vendor/` is shared between the services, so re-run `composer install` when
switching versions.

To rebuild after Dockerfile changes: `docker compose build`

Xdebug is installed in every image. Prefix a command with `-e XDEBUG_MODE=off`
(e.g. `docker compose run --rm -e XDEBUG_MODE=off php84 vendor/bin/phpunit`)
for faster runs.
