# Bob

Use 'make' commands in your filament plugins.

## Installation

You can install the package via composer:

```bash
composer global require jonquihote/filament-bob
```

You can publish the config file with:

```bash
bob install
```

This is the contents of the published config file:

```php
return [

    'namespace' => 'App\\',

    'view_namespace' => '',

];
```

## Usage

```php
bob make:resource
bob make:page
bob make:widget
```

## Credits

- [Joni Chandra](https://github.com/jonquihote)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
