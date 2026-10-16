# CHANGELOG

## 1.0.0 - 2026-10-16

First release of `pieceofcake2/acl_menu`, a maintained fork of
[markstory/cakephp_menu_component](https://github.com/markstory/cakephp_menu_component), ported from
CakePHP 1.3 to CakePHP 2.

- CakePHP 2 plugin layout (`Controller/Component/MenuComponent.php`); the component is still
  `AclMenu.Menu`. Requires PHP 8.0+ and pieceofcake2/cakephp 2.12+
- ACO paths match `ActionsAuthorize` and the AclExtras plugin: `controllers/Posts/add`,
  `controllers/Blog/Posts/add` for plugins (the 1.3 version used `controllers/posts/add`)
- Routing prefixes from `Routing.prefixes` (CakePHP 2) instead of `Routing.admin`
- The ARO of the user in the session is `[userModel => user]` (new `userModel` setting)
- New `plugins` setting to include the controllers of plugins, and `menuVar` for the name of the
  view variable
- The menu items are sorted by `weight` (stable) at every level, not only the top level
- Each ACO is checked once per menu
- The menu tree functions (ids, ACO paths, merging, sorting) are in the `MenuTree` class (`AclMenu.Lib`)
- Fixes: `clearCache()` clears the cached menus of all AROs; items added with `addMenu()` no longer
  prevent the controllers from being read; items without a controller URL (e.g. external links) are
  shown without an ACL check instead of causing errors
- PHPUnit tests, PHP_CodeSniffer and PHPStan (level 5) in CI
- Documentation in English and Japanese
