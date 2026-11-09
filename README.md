<div align="center">
    <h1>Carabdheer Laravel</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/zifala/carabdheer-laravel"><img src="https://img.shields.io/packagist/v/zifala/carabdheer-laravel.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/zifala/carabdheer-laravel"><img src="https://img.shields.io/packagist/php-v/zifala/carabdheer-laravel.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/zifala/carabdheer-laravel"><img src="https://badge.laravel.cloud/badge/zifala/carabdheer-laravel?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/zifala/carabdheer-laravel/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/zifala/carabdheer-laravel/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/zifala/carabdheer-laravel"><img src="https://img.shields.io/packagist/dt/zifala/carabdheer-laravel.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Send exceptions and events from a Laravel app to Carabdheer.

## Installation

You can install the package via Composer:

```bash
composer require zifala/carabdheer-laravel
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="carabdheer-laravel"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="carabdheer-laravel-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="carabdheer-laravel-migrations"
php artisan migrate
```

## Usage

<!-- Add a basic usage example here. -->

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Carabdheer Laravel! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Zifala](https://github.com/zifala)
- [All Contributors](../../contributors)

## License

Carabdheer Laravel is open-sourced software licensed under the [MIT license](LICENSE.md).
