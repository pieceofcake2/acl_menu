<?php
/**
 * Menu Component
 *
 * Uses ACL to generate Menus.
 *
 * Copyright 2008, Mark Story.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2008, Mark Story.
 * @link https://mark-story.com
 * @author Mark Story <mark@mark-story.com>
 * @license https://opensource.org/licenses/mit-license.php The MIT License
 */

App::uses('Component', 'Controller');

/**
 * Builds a navigation menu of the controller actions the current user may access, checked with
 * `AclComponent` (DbAcl and the ACO tree created by the AclExtras plugin).
 *
 * The menu is set to the view as `$menu` (a tree of `id`, `parent`, `title`, `url`, `weight` and
 * `children`) and can be rendered with the MenuBuilder plugin.
 *
 * Controllers can set `$menuOptions`:
 *
 * - `exclude`: Actions not in the menu. `['*']` excludes the whole controller.
 * - `parent`: Id of the parent menu item of the controller.
 * - `alias`: Titles of the actions, as `action => title`.
 * - `controllerButton`: Add a menu item for the controller with the actions as children (default true).
 *
 * @property AclComponent $Acl
 * @property AuthComponent $Auth
 */
class MenuComponent extends Component
{
    /**
     * The default parent of the menu items of controllers that have no `parent` option.
     *
     * @var string|null
     */
    public $defaultMenuParent = null;

    /**
     * Build the menu in startup(). Set to false to build menus for other AROs than the user in
     * the session: call loadCache(), constructMenu() and writeCache() yourself.
     *
     * @var bool
     */
    public $autoLoad = true;

    /**
     * Controller reference.
     *
     * @var Controller|null
     */
    public $Controller = null;

    /**
     * Components used by Menu.
     *
     * @var array
     */
    public $components = ['Acl', 'Auth'];

    /**
     * Cache key of the menu items of all controllers.
     *
     * @var string
     */
    public $cacheKey = 'menu_storage';

    /**
     * Time to cache menus for (strtotime() compatible string).
     *
     * @var string
     */
    public $cacheTime = '+1 day';

    /**
     * Cache configuration. Created with the File engine if it does not exist.
     *
     * @var string
     */
    public $cacheConfig = 'menu_component';

    /**
     * Separator between the controller and the action in the ACO path.
     *
     * @var string
     */
    public $aclSeparator = '/';

    /**
     * ACO path of the controllers (the `actionPath` of ActionsAuthorize).
     *
     * @var string
     */
    public $aclPath = 'controllers/';

    /**
     * User model, to identify the ARO of the user in the session.
     *
     * @var string
     */
    public $userModel = 'User';

    /**
     * Include the controllers of plugins: false (default), true for all loaded plugins, or a list
     * of plugin names.
     *
     * @var array|bool
     */
    public $plugins = false;

    /**
     * Name of the view variable with the menu.
     *
     * @var string
     */
    public $menuVar = 'menu';

    /**
     * Actions to exclude from menus. Per controller exclusions can be set with Controller::$menuOptions.
     *
     * @var array
     */
    public $excludeActions = ['view', 'edit', 'delete'];

    /**
     * Completed list of methods to not include in menus. Includes all of Controller's methods.
     *
     * @var array
     */
    public $excludedMethods = [];

    /**
     * The completed menu for the current user.
     *
     * @var array
     */
    public $menu = [];

    /**
     * Raw menus before formatting, from the controllers or the cache.
     *
     * @var array
     */
    public $rawMenus = [];

    /**
     * Whether menu items were added to a cached menu set and the menus must be rebuilt.
     *
     * @var bool
     */
    protected $_rebuildMenus = false;

    /**
     * Whether the raw menus were loaded from the cache.
     *
     * @var bool
     */
    protected $_cacheLoaded = false;

    /**
     * Initialize: apply the settings.
     *
     * @param Controller $controller Controller.
     * @return void
     */
    public function initialize(Controller $controller)
    {
        $this->Controller = $controller;
        if (!in_array($this->cacheConfig, Cache::configured(), true)) {
            Cache::config($this->cacheConfig, [
                'engine' => 'File',
                'duration' => $this->cacheTime,
                'prefix' => 'acl_menu_',
            ]);
        }
    }

    /**
     * Startup: build the menu of the user in the session.
     *
     * @param Controller $controller Controller.
     * @return void
     */
    public function startup(Controller $controller)
    {
        $this->Controller = $controller;
        $user = $this->Auth->user();
        if (!$user || !$this->autoLoad) {
            return;
        }
        [, $model] = pluginSplit($this->userModel);
        $this->loadCache();
        $this->constructMenu([$model => $user]);
        $this->writeCache();
    }

    /**
     * Write the raw menus to the cache.
     *
     * @return bool
     */
    public function writeCache()
    {
        if (Cache::write($this->cacheKey, ['menus' => $this->rawMenus], $this->cacheConfig)) {
            return true;
        }
        $this->log('Menu Component - Could not write Menu cache.');

        return false;
    }

    /**
     * Load the raw menus from the cache and merge the menus added with addMenu().
     *
     * @return bool True if the cache was loaded.
     */
    public function loadCache()
    {
        $data = Cache::read($this->cacheKey, $this->cacheConfig);
        if (is_array($data) && isset($data['menus'])) {
            $this->rawMenus = $this->_mergeMenuCache($data['menus']);
            $this->_cacheLoaded = true;

            return true;
        }
        $this->_rebuildMenus = true;

        return false;
    }

    /**
     * Clear the cached menus (the raw menus and the menus of all AROs), e.g. after changing
     * the permissions or the controllers.
     *
     * @return bool
     */
    public function clearCache()
    {
        return Cache::clear(false, $this->cacheConfig);
    }

    /**
     * Construct the menu of an ARO from the raw menus and the permissions. The result is cached per ARO.
     *
     * @param array|string $aro ARO alias or identification array, e.g. `['User' => ['id' => 1]]`.
     * @return void Sets $this->menu.
     */
    public function constructMenu($aro)
    {
        $aroKey = $aro;
        if (is_array($aro)) {
            $model = (string)key($aro);
            $aroKey = $model . ($aro[$model]['id'] ?? '');
        }
        $cacheKey = Inflector::slug((string)$aroKey, '_') . '_' . $this->cacheKey;
        $completeMenu = Cache::read($cacheKey, $this->cacheConfig);
        if (!is_array($completeMenu) || $this->_rebuildMenus) {
            if (!$this->_cacheLoaded) {
                $added = $this->rawMenus;
                $this->rawMenus = [];
                $this->generateRawMenus();
                $this->rawMenus = $this->_mergeMenus($this->rawMenus, $added);
            }
            $menu = $allowed = [];
            foreach ($this->rawMenus as $item) {
                if (isset($menu[$item['id']])) {
                    continue;
                }
                $aco = $this->_aco($item);
                if ($aco !== null && !isset($allowed[$aco])) {
                    $allowed[$aco] = (bool)$this->Acl->check($aro, $aco);
                }
                if ($aco === null || $allowed[$aco]) {
                    $menu[$item['id']] = $item;
                }
            }
            $completeMenu = $this->_formatMenu($menu);
            Cache::write($cacheKey, $completeMenu, $this->cacheConfig);
        }
        $this->menu = $completeMenu;
    }

    /**
     * Generate the raw menus from the controllers of the application (and plugins).
     *
     * @return void Sets $this->rawMenus.
     */
    public function generateRawMenus()
    {
        $this->createExclusions();
        $prefixes = (array)Configure::read('Routing.prefixes');

        foreach ($this->getControllers() as $plugin => $controllers) {
            $plugin = $plugin === '' ? null : $plugin;
            $dotPlugin = $plugin ? $plugin . '.' : '';
            App::uses($plugin . 'AppController', $dotPlugin . 'Controller');
            foreach ($controllers as $controller) {
                $name = preg_replace('/Controller$/', '', $controller);
                if ($name === $plugin . 'App' || $name === 'App') {
                    continue;
                }
                App::uses($controller, $dotPlugin . 'Controller');
                if (!class_exists($controller)) {
                    continue;
                }
                $this->_controllerMenus($controller, $name, $plugin, $prefixes);
            }
        }
    }

    /**
     * Get the controllers, grouped by plugin ('' for the application).
     *
     * @return array<string, array<string>> Controller class names by plugin.
     */
    public function getControllers()
    {
        $controllers = ['' => App::objects('Controller', null, false)];
        $plugins = $this->plugins === true ? CakePlugin::loaded() : ($this->plugins ?: []);
        foreach ($plugins as $plugin) {
            $controllers[$plugin] = App::objects($plugin . '.Controller', null, false);
        }

        return $controllers;
    }

    /**
     * Filter out methods based on $menuOptions. Removes protected methods (starting with `_`) too.
     *
     * @param array $methods Methods.
     * @param array $remove Additional methods to remove, normally options of the controller.
     * @return array
     */
    public function filterMethods($methods, $remove = [])
    {
        $exclusions = array_merge($this->excludedMethods, array_map('strtolower', (array)$remove));
        $result = [];
        foreach ($methods as $method) {
            $lower = strtolower($method);
            if (str_starts_with($lower, '_') || in_array($lower, $exclusions, true)) {
                continue;
            }
            $result[] = $method;
        }

        return $result;
    }

    /**
     * Set the options of a controller.
     *
     * @param array $controllerVars Class variables of the controller.
     * @return array|false Options, or false if the controller is excluded.
     */
    public function setOptions($controllerVars)
    {
        $menuOptions = $controllerVars['menuOptions'] ?? [];
        $exclude = [];
        foreach ((array)Configure::read('Routing.prefixes') as $prefix) {
            foreach ($this->excludeActions as $action) {
                $exclude[] = $prefix . '_' . $action;
            }
        }

        $defaults = [
            'exclude' => $exclude,
            'alias' => [],
            'parent' => $this->defaultMenuParent,
            'controllerButton' => true,
        ];
        $menuOptions = Hash::merge($defaults, (array)$menuOptions);
        if (in_array('*', (array)$menuOptions['exclude'], true)) {
            return false;
        }

        return $menuOptions;
    }

    /**
     * Create the list of methods that are never menu items: the methods of Controller and $excludeActions.
     *
     * @return void
     */
    public function createExclusions()
    {
        $methods = array_merge(get_class_methods('Controller'), $this->excludeActions);
        $this->excludedMethods = array_map('strtolower', $methods);
    }

    /**
     * Add a menu item. Items added after constructMenu() are not shown.
     *
     * - `title`: Title. Defaults to the humanized action.
     * - `url`: URL array (checked with ACL) or string (always shown).
     * - `id`: Unique id, for `parent` of other items. Defaults to `controller-action`.
     * - `parent`: Id of the parent item.
     * - `weight`: Items with a lower weight are shown first.
     *
     * @param array $menu Menu item.
     * @return void
     */
    public function addMenu($menu)
    {
        $menu += [
            'title' => null,
            'url' => null,
            'parent' => null,
            'id' => null,
            'weight' => 0,
        ];
        if (!$menu['id'] && is_array($menu['url'])) {
            $menu['id'] = $this->_createId($menu['url']);
        }
        if (!$menu['title'] && isset($menu['url']['action'])) {
            $menu['title'] = Inflector::humanize($menu['url']['action']);
        }
        $this->rawMenus[] = $menu;
    }

    /**
     * BeforeRender: set the menu to the view.
     *
     * @param Controller $controller Controller.
     * @return void
     */
    public function beforeRender(Controller $controller)
    {
        $controller->set($this->menuVar, $this->menu);
    }

    /**
     * Add the menu items of a controller.
     *
     * @param string $class Controller class name.
     * @param string $name Controller name.
     * @param string|null $plugin Plugin name.
     * @param array $prefixes Routing prefixes.
     * @return void
     */
    protected function _controllerMenus($class, $name, $plugin, array $prefixes)
    {
        $menuOptions = $this->setOptions(get_class_vars($class));
        if ($menuOptions === false) {
            return;
        }
        $methods = $this->filterMethods(get_class_methods($class), $menuOptions['exclude']);
        $controller = Inflector::underscore($name);
        $pluginUrl = $plugin ? Inflector::underscore($plugin) : null;
        $controllerId = $this->_createId(array_filter([$pluginUrl, $controller]));
        $parent = $menuOptions['controllerButton'] ? $controllerId : $menuOptions['parent'];
        $indexPrefix = null;

        foreach ($methods as $action) {
            $url = ['plugin' => $pluginUrl, 'controller' => $controller, 'action' => $action];
            foreach ($prefixes as $prefix) {
                $url[$prefix] = false;
                if (str_starts_with($action, $prefix . '_')) {
                    $url[$prefix] = true;
                    if ($action === $prefix . '_index') {
                        $indexPrefix = $prefix;
                    }
                }
            }
            $this->rawMenus[] = [
                'parent' => $parent,
                'id' => $this->_createId(array_filter([$pluginUrl, $controller, $action])),
                'title' => $menuOptions['alias'][$action] ?? Inflector::humanize(Inflector::underscore($action)),
                'url' => $url,
                'weight' => 0,
            ];
        }

        if ($menuOptions['controllerButton']) {
            // Use the prefixed index (e.g. admin_index) if the controller has one.
            $url = ['plugin' => $pluginUrl, 'controller' => $controller, 'action' => $indexPrefix ? $indexPrefix . '_index' : 'index'];
            foreach ($prefixes as $prefix) {
                $url[$prefix] = $prefix === $indexPrefix;
            }
            $this->rawMenus[] = [
                'parent' => $menuOptions['parent'],
                'id' => $controllerId,
                'title' => Inflector::humanize(Inflector::underscore($name)),
                'url' => $url,
                'weight' => 0,
            ];
        }
    }

    /**
     * The ACO path of a menu item, or null if the item is not checked (no URL array with a controller).
     *
     * @param array $item Menu item.
     * @return string|null
     */
    protected function _aco(array $item)
    {
        $url = $item['url'] ?? null;
        if (!is_array($url) || empty($url['controller'])) {
            return null;
        }
        $path = $this->aclPath;
        if (!empty($url['plugin'])) {
            $path .= Inflector::camelize($url['plugin']) . '/';
        }
        $path .= Inflector::camelize($url['controller']);
        if (isset($url['action'])) {
            $path .= $this->aclSeparator . $url['action'];
        }

        return $path;
    }

    /**
     * Make a unique menu item id.
     *
     * @param array|string ...$parts Parts, or one array of parts.
     * @return string
     */
    protected function _createId(...$parts)
    {
        if (is_array($parts[0])) {
            $parts = $parts[0];
            if (isset($parts['controller'])) {
                $parts = array_filter([$parts['plugin'] ?? null, $parts['controller'], $parts['action'] ?? null]);
            }
        }

        return Inflector::variable(implode('-', $parts));
    }

    /**
     * Build the menu tree.
     *
     * @param array $menu Menu items by id.
     * @return array
     */
    protected function _formatMenu($menu)
    {
        $out = $idMap = [];
        foreach ($menu as $item) {
            $id = $item['id'];
            $parentId = $item['parent'];
            if (isset($idMap[$id]['children'])) {
                $idMap[$id] = array_merge($item, ['children' => $idMap[$id]['children']]);
            } else {
                $idMap[$id] = array_merge($item, ['children' => []]);
            }
            if ($parentId) {
                $idMap[$parentId]['children'][] =& $idMap[$id];
            } else {
                $out[] =& $idMap[$id];
            }
        }
        // Drop the references, so that the menu can be cached and changed safely.
        $out = unserialize(serialize($out));

        return $this->_sortMenu($out);
    }

    /**
     * Sort menu items (and their children) by weight, keeping the order of items with the same weight.
     *
     * @param array $items Menu items.
     * @return array
     */
    protected function _sortMenu(array $items)
    {
        usort($items, fn($one, $two) => ($one['weight'] ?? 0) <=> ($two['weight'] ?? 0));
        foreach ($items as &$item) {
            if (!empty($item['children'])) {
                $item['children'] = $this->_sortMenu($item['children']);
            }
        }

        return $items;
    }

    /**
     * Merge the cached menus with the menus added with addMenu(), keeping the ids unique.
     *
     * @param array $cachedMenus Cached menus.
     * @return array Merged menus.
     */
    protected function _mergeMenuCache($cachedMenus)
    {
        $merged = $this->_mergeMenus($cachedMenus, $this->rawMenus);
        if (count($merged) > count($cachedMenus)) {
            $this->_rebuildMenus = true;
        }

        return $merged;
    }

    /**
     * Append the items whose id is not in the menus yet.
     *
     * @param array $menus Menus.
     * @param array $added Items to add.
     * @return array
     */
    protected function _mergeMenus(array $menus, array $added)
    {
        $ids = array_flip(array_column($menus, 'id'));
        foreach ($added as $item) {
            if (!isset($ids[$item['id']])) {
                $menus[] = $item;
                $ids[$item['id']] = true;
            }
        }

        return $menus;
    }
}
