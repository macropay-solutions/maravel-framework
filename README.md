# Maravel Framework (Kernel)

[![Total Downloads](https://img.shields.io/packagist/dt/macropay-solutions/maravel-framework)](https://packagist.org/packages/macropay-solutions/maravel-framework)
[![Latest Stable Version](https://img.shields.io/packagist/v/macropay-solutions/maravel-framework)](https://packagist.org/packages/macropay-solutions/maravel-framework)
[![License](https://img.shields.io/packagist/l/macropay-solutions/maravel-framework)](https://packagist.org/packages/macropay-solutions/maravel-framework)

> **Note:** This repository contains the core code of the Macropay-Solutions Maravel framework. If you want to build an application using macropay-solutions Maravel, visit the main [Maravel template repository](https://github.com/macropay-solutions/maravel).

## Maravel PHP Framework

Macropay-Solutions Maravel is an improvement inspired by Lumen  10.0.4 and Laravel packages v10.48.29

## Official Documentation

Documentation for the framework can be found on the [Lumen website](https://lumen.laravel.com/docs/10.x).

## Contributing

We plan to not change the code that often and allow you to build packages that use the DI container to extend functionality and also allow retroactive bug fixing.

To maintain a stable core with no new overhead that does not need to be updated yearly, we plan to only update in the future for the following reasons:

- PHP version,
- Symfony components LTS versions,
- Possible missed classes that would need to be resolved instead of instantiated directly.

Current version is using Symfony components 6.4 (LTS) and supports PHP 8.1 to 8.3 which are supported until the end of 2027.

We remain open for suggestions in the discussions area: https://github.com/macropay-solutions/maravel-framework/discussions

> **Note:**
>
> Classes that use the Macroable trait are resolved from DI container instead of being instantiated with new.
>
> The Illuminate namespace can still be used to keep the compatibility with laravel packages.
>
> The (faster) Container is accepting also list when resolving the classes with parameters on construct.
>
> [Maravel template 10.50.0](https://github.com/macropay-solutions/maravel) adds config:cache and route:cache features without changes in the framework.

## Security Vulnerabilities

Please review [our security policy](https://github.com/macropay-solutions/maravel-framework/security/policy) on how to report security vulnerabilities.

## License

Macropay-Solutions Maravel is open-sourced software licensed under the [MIT license](LICENSE).
