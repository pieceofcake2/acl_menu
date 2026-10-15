<?php

/**
 * Functions for the menu items of MenuComponent: ids, ACO paths, merging and the menu tree.
 */
class MenuTree
{
    /**
     * The ACO path of a menu item, or null if the item is not checked (no URL array with a controller).
     *
     * @param array $item Menu item.
     * @param string $aclPath ACO path of the controllers, with a trailing `/`.
     * @param string $separator Separator between the controller and the action.
     * @return string|null
     */
    public static function aco(array $item, string $aclPath, string $separator): ?string
    {
        $url = $item['url'] ?? null;
        if (!is_array($url) || empty($url['controller'])) {
            return null;
        }
        $path = $aclPath;
        if (!empty($url['plugin'])) {
            $path .= Inflector::camelize($url['plugin']) . '/';
        }
        $path .= Inflector::camelize($url['controller']);
        if (isset($url['action'])) {
            $path .= $separator . $url['action'];
        }

        return $path;
    }

    /**
     * Make a unique menu item id.
     *
     * @param array|string ...$parts Parts, or one array of parts.
     * @return string
     */
    public static function createId(...$parts): string
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
    public static function format(array $menu): array
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

        return static::sort($out);
    }

    /**
     * Sort menu items (and their children) by weight, keeping the order of items with the same weight.
     *
     * @param array $items Menu items.
     * @return array
     */
    public static function sort(array $items): array
    {
        usort($items, fn($one, $two) => ($one['weight'] ?? 0) <=> ($two['weight'] ?? 0));
        foreach ($items as &$item) {
            if (!empty($item['children'])) {
                $item['children'] = static::sort($item['children']);
            }
        }

        return $items;
    }

    /**
     * Append the items whose id is not in the menus yet.
     *
     * @param array $menus Menus.
     * @param array $added Items to add.
     * @return array
     */
    public static function merge(array $menus, array $added): array
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

    /**
     * The URL of an action, with every routing prefix set (true only for the prefix of the action).
     *
     * @param string|null $plugin Plugin (underscored).
     * @param string $controller Controller (underscored).
     * @param string $action Action (method name).
     * @param array $prefixes Routing prefixes.
     * @return array
     */
    public static function url(?string $plugin, string $controller, string $action, array $prefixes): array
    {
        $url = ['plugin' => $plugin, 'controller' => $controller, 'action' => $action];
        foreach ($prefixes as $prefix) {
            $url[$prefix] = str_starts_with($action, $prefix . '_');
        }

        return $url;
    }

    /**
     * The action of the controller item: the first prefixed index (e.g. admin_index) if the
     * controller has one, otherwise index.
     *
     * @param array $methods Actions of the controller.
     * @param array $prefixes Routing prefixes.
     * @return string
     */
    public static function indexAction(array $methods, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            if (in_array($prefix . '_index', $methods, true)) {
                return $prefix . '_index';
            }
        }

        return 'index';
    }
}
