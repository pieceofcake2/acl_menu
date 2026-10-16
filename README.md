# CakePHP 2 AclMenu Plugin

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/acl_menu?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/acl_menu?label=Packagist)](https://packagist.org/packages/pieceofcake2/acl_menu)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/acl_menu/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/acl_menu/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/acl_menu/actions/workflows/CI.yml)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/acl_menu/CI.yml?label=CI)](https://github.com/pieceofcake2/acl_menu/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/acl_menu?label=Coverage)](https://codecov.io/gh/pieceofcake2/acl_menu)

__This is forked for CakePHP2.__

A component for [pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) applications that
builds a navigation menu of the controller actions the current user may access, checked with ACL
(the same ACOs as `ActionsAuthorize` and the [AclExtras](https://github.com/pieceofcake2/acl_extras)
plugin). The menu can be rendered with the [MenuBuilder](https://github.com/pieceofcake2/menu_builder) plugin.

## Documentation

- Website: https://pieceofcake2.github.io/acl_menu/ (日本語: https://pieceofcake2.github.io/acl_menu/ja/)
- Markdown: [docs/README.md](docs/README.md) (日本語: [docs/ja/README.md](docs/ja/README.md)) (for AI tools and offline reading)

It covers the ACL requirements, rendering, the settings, `$menuOptions`, adding items, prefixes and
plugins, caching and the migration from the CakePHP 1.3 version.

The Markdown documentation is also included in the Composer package, in the plugin's directory: `plugins/AclMenu/docs/README.md` (the location depends on your `installer-paths`).

## Requirements

* PHP 8.0+
* pieceofcake2/cakephp 2.12+

## Installation

```bash
composer require pieceofcake2/acl_menu
```

Load the plugin in `config/bootstrap.php` (legacy layout: `app/Config/bootstrap.php`):

```php
CakePlugin::load('AclMenu');
```

See [Installation](docs/README.md#installation) for the `installer-paths` setting.

## Usage

```php
public $components = [
    'Acl',
    'Auth' => ['authorize' => ['Actions' => ['actionPath' => 'controllers']]],
    'AclMenu.Menu',
];

public $helpers = ['MenuBuilder.MenuBuilder'];
```

```php
// templates/Layouts/default.ctp
echo $this->MenuBuilder->build();
```

The component sets the menu of the logged-in user to the view as `$menu`. See
[Usage](docs/README.md#usage).

## Contributing

See the [contribution guidelines](https://github.com/pieceofcake2/.github/blob/master/CONTRIBUTING.md).

## Origin

This package is a maintained fork of
[markstory/cakephp_menu_component](https://github.com/markstory/cakephp_menu_component), the ACL menu
component by Mark Story for CakePHP 1.3, ported to CakePHP 2. It is maintained for CakePHP 2 on
modern PHP by [pieceofcake2](https://github.com/pieceofcake2). Changes from the original are
listed in the
[commit history since the fork](https://github.com/pieceofcake2/acl_menu/compare/9887861...1.x).

## License

Licensed under the MIT License. See [LICENSE](LICENSE).
