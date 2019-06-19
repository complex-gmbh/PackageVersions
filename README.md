# Package Versions

This utility provides quick and easy access to version information of composer dependencies.

This information is derived from the ```composer.lock``` file which is (re)generated during ```composer install``` or ```composer update```.

```php
$version = \PackageVersions\Versions::getVersion('ocramius/package-versions');

var_dump($version); // 1.0.0@0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33
```

### Installation

```sh
composer require complex/package-versions
```

It is suggested that you use [an optimized composer autoloader](https://getcomposer.org/doc/06-config.md#optimize-autoloader) (to prevent autoload I/O when accessing the `PackageVersions\Versions` API) in your composer.json:
```
...
    "config": {
        "optimize-autoloader": true
    },
...
```

In case you manually generate your autoloader via the CLI use the `--optimize` flag:

```sh
composer dump-autoload --optimize
```

### Use-cases

This repository implements `PackageVersions\Versions::getVersion()` in such a way that no IO
happens when calling it, because the list of package versions is compiled during composer
installation.

This is especially useful when you want to generate assets/code/artifacts that are computed from
the current version of a certain dependency. Doing so at runtime by checking the installed
version of a package would be too expensive, and this package mitigates that.

## Professional Support

[Professionally supported `ocramius/package-versions` is available through Tidelift](https://tidelift.com/subscription/pkg/packagist-ocramius-package-versions?utm_source=packagist-ocramius-package-versions&utm_medium=referral&utm_campaign=readme).

You can also contact the maintainer at ocramius@gmail.com for looking into issues related to this package
in your private projects.
