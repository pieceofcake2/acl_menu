# AclMenu Plugin Documentation

The AclMenu plugin provides a component that builds a navigation menu of the controller actions the
current user may access. The permissions are checked with `AclComponent` (DbAcl), with the same
ACO paths as `ActionsAuthorize` and the [AclExtras](https://pieceofcake2.github.io/acl_extras/)
plugin, so the menu shows exactly the actions the user is allowed to run.

The menu is set to the view as `$menu`, a tree of items that the
[MenuBuilder](https://pieceofcake2.github.io/menu_builder/) plugin can render as is.

- [Installation](#installation)
- [Requirements for the ACL](#requirements-for-the-acl)
- [Usage](#usage)
- [Rendering the Menu](#rendering-the-menu)
- [Settings](#settings)
- [Controller Options ($menuOptions)](#controller-options-menuoptions)
- [Adding Menu Items](#adding-menu-items)
- [Prefixes and Plugins](#prefixes-and-plugins)
- [Caching](#caching)
- [Menus for Other AROs](#menus-for-other-aros)
- [Migrating from the CakePHP 1.3 Version](#migrating-from-the-cakephp-1-3-version)
- [Origin](#origin)

## Installation

Requirements: PHP 8.0+ and [pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) 2.12+.

```bash
composer require pieceofcake2/acl_menu
```

The plugin has the `cakephp-plugin` type, so it is installed into your plugins directory by
`composer/installers`. Set `installer-paths` in your application's `composer.json`:

| Layout | `installer-paths` |
| --- | --- |
| Modern (`config/`, `src/`, `templates/`, `plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
| Legacy (`app/Config/`, `app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

Load the plugin in `config/bootstrap.php` (legacy layout: `app/Config/bootstrap.php`). If you
already use `CakePlugin::loadAll()`, this is not necessary:

```php
CakePlugin::load('AclMenu');
```

## Requirements for the ACL

The component uses the [Access Control Lists](https://pieceofcake2.github.io/docs/core-libraries/components/access-control-lists)
of CakePHP 2:

- `AuthComponent` with a logged-in user, and the user model acting as ARO (`Acl` behavior,
  `requester`).
- ACO nodes for the controllers and actions under `controllers`, for example
  `controllers/Posts/add`. Create them with the AclExtras plugin:
  `bin/cake AclExtras.AclExtras aco_sync`.
- Permissions for the AROs (users or groups), for example with `$this->Acl->allow()`.

These are the same ACOs that `'authorize' => ['Actions']` of `AuthComponent` checks.

## Usage

Add the component, for example in `AppController`:

```php
class AppController extends Controller
{
    public $components = [
        'Session',
        'Acl',
        'Auth' => [
            'authorize' => ['Actions' => ['actionPath' => 'controllers']],
        ],
        'AclMenu.Menu',
    ];
}
```

On every request with a logged-in user, the component reads the actions of the application's
controllers, keeps the ones the user may access, and sets the menu to the view as `$menu`:

```php
[
    [
        'id' => 'posts',
        'parent' => null,
        'title' => 'Posts',
        'url' => ['plugin' => null, 'controller' => 'posts', 'action' => 'index', 'admin' => false],
        'weight' => 0,
        'children' => [
            ['id' => 'posts-index', 'title' => 'Index', 'url' => [/* ... */], 'children' => [] /* ... */],
            ['id' => 'posts-add', 'title' => 'Add', 'url' => [/* ... */], 'children' => [] /* ... */],
        ],
    ],
    // ...
]
```

Each controller is a menu item (linking to its `index`) with its actions as children. The menu is
cached per user, see [Caching](#caching).

## Rendering the Menu

With the [MenuBuilder](https://pieceofcake2.github.io/menu_builder/) plugin, which reads the `$menu`
view variable by default:

```php
public $helpers = ['MenuBuilder.MenuBuilder'];
```

```php
// templates/Layouts/default.ctp (legacy layout: app/View/Layouts/default.ctp)
echo $this->MenuBuilder->build();
```

Or render it yourself, for example with an element:

```php
<?php // templates/Elements/menu.ctp (legacy layout: app/View/Elements/menu.ctp) ?>
<ul>
    <?php foreach ($items as $item) : ?>
        <li>
            <?= $this->Html->link($item['title'], $item['url']) ?>
            <?php if ($item['children']) : ?>
                <?= $this->element('menu', ['items' => $item['children']]) ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
```

```php
echo $this->element('menu', ['items' => $menu]);
```

## Settings

Pass the settings in `$components`, for example `'AclMenu.Menu' => ['plugins' => true]`.

| Setting | Default | Description |
| --- | --- | --- |
| `userModel` | `User` | User model of the ARO: the ARO of the logged-in user is `[userModel => user data]`. |
| `aclPath` | `controllers/` | ACO path of the controllers (the `actionPath` of `ActionsAuthorize`, with a trailing `/`). |
| `aclSeparator` | `/` | Separator between the controller and the action in the ACO path. |
| `plugins` | `false` | Include the controllers of plugins: `true` for all loaded plugins, or a list of plugin names. |
| `excludeActions` | `['view', 'edit', 'delete']` | Actions that are never menu items (also with each routing prefix, e.g. `admin_edit`). |
| `defaultMenuParent` | `null` | Parent id of the controller items without a `parent` option. |
| `menuVar` | `menu` | Name of the view variable. |
| `autoLoad` | `true` | Build the menu of the logged-in user in `startup()`. See [Menus for Other AROs](#menus-for-other-aros). |
| `cacheConfig` | `menu_component` | Cache configuration. Created with the File engine if it does not exist. |
| `cacheKey` | `menu_storage` | Cache key. |
| `cacheTime` | `+1 day` | Duration of the cache configuration created by the component. |

Actions are the public methods of a controller, without the methods of `Controller` (callbacks
such as `beforeFilter()`, `render()`, ...), methods starting with `_`, and the excluded actions.

## Controller Options ($menuOptions)

A controller can set `$menuOptions`:

```php
class CommentsController extends AppController
{
    public $menuOptions = [
        'parent' => 'posts',
        'exclude' => ['export'],
        'alias' => ['index' => 'All comments'],
    ];
}
```

| Option | Description |
| --- | --- |
| `exclude` | Actions of the controller that are not menu items, in addition to `excludeActions` and their prefixed versions (`admin_view`, `admin_edit`, ...). `['*']` excludes the whole controller. |
| `parent` | Id of the parent item of the controller item, for example the id of another controller (`posts`). |
| `alias` | Titles of the actions, as `action => title`. |
| `controllerButton` | `false` to add the actions as top-level items (or under `parent`) without a controller item. Default `true`. |

The ids are `controller` (e.g. `posts`, `blogPosts`) for controllers and `controller-action`
(e.g. `posts-add`, `posts-adminIndex`) for actions, with the plugin first for plugin controllers
(`blog-posts-add`).

## Adding Menu Items

Add other items in `beforeFilter()`, before the menu is built in `startup()`:

```php
/**
 * Before filter.
 *
 * @return void
 */
public function beforeFilter()
{
    parent::beforeFilter();
    $this->Menu->addMenu([
        'title' => __('Dashboard'),
        'url' => ['controller' => 'users', 'action' => 'dashboard'],
        'weight' => -10,
    ]);
    $this->Menu->addMenu([
        'id' => 'help',
        'title' => __('Help'),
        'url' => 'https://example.com/help',
        'weight' => 100,
    ]);
}
```

| Key | Description |
| --- | --- |
| `title` | Title. Defaults to the humanized action. |
| `url` | A URL array is checked with ACL like the controller actions. A string URL (or no URL) is always shown. |
| `id` | Unique id. Defaults to `controller-action`. |
| `parent` | Id of the parent item. |
| `weight` | Items with a lower weight are shown first; items with the same weight keep their order. |

## Prefixes and Plugins

Actions with a routing prefix of `Routing.prefixes` (e.g. `admin_index`) get the prefix in the URL
(`'admin' => true`), and the other actions get `'admin' => false`. The controller item links to
the prefixed index (e.g. `admin_index`) if the controller has one.

Set `plugins` to include the controllers of plugins. Their ACO paths contain the plugin name
(`controllers/Blog/Posts/add`) and their URLs the `plugin` key.

## Caching

Reading the controllers is slow, so the component caches:

- the menu items of all controllers (`cacheKey`), and
- the menu of each ARO (`<Aro><id>_<cacheKey>`, e.g. `User1_menu_storage`).

Clear the cache after changing the permissions, the controllers or `$menuOptions`:

```php
$this->Menu->clearCache();
```

`clearCache()` clears the whole cache configuration of the component, so use a cache configuration
of its own (the default `menu_component`). In development, a short `duration` in your own
configuration is convenient:

```php
Cache::config('menu_component', ['engine' => 'File', 'duration' => '+1 minute', 'prefix' => 'acl_menu_']);
```

When items are added with `addMenu()` that are not in the cache, the menus are rebuilt.

## Menus for Other AROs

Set `autoLoad` to false and build the menu yourself, for example for a group. Build it before the
component sets the view variable in its `beforeRender()`, for example in `beforeFilter()`:

```php
public $components = ['AclMenu.Menu' => ['autoLoad' => false]];

/**
 * Before filter.
 *
 * @return void
 */
public function beforeFilter()
{
    parent::beforeFilter();
    if (!$this->Auth->user()) {
        return;
    }
    $this->Menu->loadCache();
    $this->Menu->constructMenu(['Group' => ['id' => $this->Auth->user('group_id')]]);
    $this->Menu->writeCache();
}
```

`constructMenu()` takes an ARO alias or an identification array, like `AclComponent::check()`.

## Migrating from the CakePHP 1.3 Version

This package is a port of the CakePHP 1.3 component `markstory/cakephp_menu_component` (installed
as `app/plugins/acl_menu`). The component is still `AclMenu.Menu`, and the settings and methods
have the same names. Changes:

- ACO paths use CamelCase controller names, like `ActionsAuthorize` and AclExtras:
  `controllers/Posts/add` instead of `controllers/posts/add`.
- Routing prefixes are read from `Routing.prefixes` instead of `Routing.admin`.
- The ARO of the logged-in user is `[userModel => user data]` (`userModel` defaults to `User`).
- Menu URLs contain the `plugin` key and every routing prefix.
- Menu ids of prefixed actions are camelized (`posts-adminIndex`), as `Inflector::variable()` does
  in CakePHP 2.

## Origin

This package is a maintained fork of
[markstory/cakephp_menu_component](https://github.com/markstory/cakephp_menu_component), the ACL menu
component by Mark Story for CakePHP 1.3, ported to CakePHP 2. It is maintained for CakePHP 2 on
modern PHP by [pieceofcake2](https://github.com/pieceofcake2). Changes from the original are
listed in the
[commit history since the fork](https://github.com/pieceofcake2/acl_menu/compare/9887861...1.x).
