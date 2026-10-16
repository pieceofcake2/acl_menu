# AclMenu プラグインドキュメント

AclMenu プラグインは、現在のユーザーがアクセスできるコントローラのアクションから、ナビゲーション
メニューを作るコンポーネントを提供します。権限は `AclComponent` (DbAcl) で確認します。ACO のパスは
`ActionsAuthorize` や [AclExtras](https://pieceofcake2.github.io/acl_extras/ja/) プラグインと同じなので、
メニューにはユーザーが実行を許可されたアクションだけが表示されます。

メニューは項目のツリーとしてビューの `$menu` にセットされ、
[MenuBuilder](https://pieceofcake2.github.io/menu_builder/ja/) プラグインでそのまま表示できます。

- [インストール](#installation)
- [ACL の前提条件](#requirements-for-the-acl)
- [使い方](#usage)
- [メニューの表示](#rendering-the-menu)
- [設定](#settings)
- [コントローラのオプション ($menuOptions)](#controller-options-menuoptions)
- [メニュー項目の追加](#adding-menu-items)
- [プレフィックスとプラグイン](#prefixes-and-plugins)
- [キャッシュ](#caching)
- [他の ARO のメニュー](#menus-for-other-aros)
- [CakePHP 1.3 版からの移行](#migrating-from-the-cakephp-1-3-version)
- [由来](#origin)

<a id="installation"></a>

## インストール

動作要件: PHP 8.0 以上、[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) 2.12 以上。

```bash
composer require pieceofcake2/acl_menu
```

プラグインのタイプは `cakephp-plugin` なので、`composer/installers` によってプラグインディレクトリに
インストールされます。アプリケーションの `composer.json` で `installer-paths` を設定してください。

| レイアウト | `installer-paths` |
| --- | --- |
| モダン (`config/`、`src/`、`templates/`、`plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
| レガシー (`app/Config/`、`app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

`config/bootstrap.php` (レガシーレイアウトでは `app/Config/bootstrap.php`) でプラグインを読み込みます。
すでに `CakePlugin::loadAll()` を使っている場合は不要です。

```php
CakePlugin::load('AclMenu');
```

<a id="requirements-for-the-acl"></a>

## ACL の前提条件

このコンポーネントは CakePHP 2 の
[アクセス制御リスト](https://pieceofcake2.github.io/docs/ja/core-libraries/components/access-control-lists)を
使います。

- `AuthComponent` でユーザーがログインしていて、ユーザーモデルが ARO になっていること
  (`Acl` ビヘイビア、`requester`)。
- `controllers` の下に、コントローラとアクションの ACO ノードがあること (たとえば `controllers/Posts/add`)。
  AclExtras プラグインで作成できます: `bin/cake AclExtras.AclExtras aco_sync`
- ARO (ユーザーまたはグループ) の権限が設定されていること (たとえば `$this->Acl->allow()` で)。

これらは、`AuthComponent` の `'authorize' => ['Actions']` が確認する ACO と同じものです。

<a id="usage"></a>

## 使い方

たとえば `AppController` にコンポーネントを追加します。

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

ユーザーがログインしているリクエストごとに、コンポーネントはアプリケーションのコントローラの
アクションを読み取り、ユーザーがアクセスできるものだけを残して、メニューをビューの `$menu` に
セットします。

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

各コントローラは (その `index` にリンクする) メニュー項目になり、アクションはその子になります。
メニューはユーザーごとにキャッシュされます。[キャッシュ](#caching)を参照してください。

<a id="rendering-the-menu"></a>

## メニューの表示

[MenuBuilder](https://pieceofcake2.github.io/menu_builder/ja/) プラグインは、デフォルトでビュー変数
`$menu` を読み込むので、そのまま表示できます。

```php
public $helpers = ['MenuBuilder.MenuBuilder'];
```

```php
// templates/Layouts/default.ctp (legacy layout: app/View/Layouts/default.ctp)
echo $this->MenuBuilder->build();
```

自分で表示することもできます。たとえばエレメントを使う場合は次のようにします。

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

<a id="settings"></a>

## 設定

設定は `$components` で渡します。たとえば `'AclMenu.Menu' => ['plugins' => true]` のようにします。

| 設定 | デフォルト | 説明 |
| --- | --- | --- |
| `userModel` | `User` | ARO のユーザーモデル。ログイン中のユーザーの ARO は `[userModel => ユーザーデータ]` です。 |
| `aclPath` | `controllers/` | コントローラの ACO のパス (`ActionsAuthorize` の `actionPath` に末尾の `/` を付けたもの)。 |
| `aclSeparator` | `/` | ACO のパスでコントローラとアクションを区切る文字。 |
| `plugins` | `false` | プラグインのコントローラも含めます。`true` で読み込み済みのすべてのプラグイン、またはプラグイン名のリスト。 |
| `excludeActions` | `['view', 'edit', 'delete']` | メニュー項目にしないアクション (各ルーティングプレフィックス付きのもの、たとえば `admin_edit` も含む)。 |
| `defaultMenuParent` | `null` | `parent` オプションのないコントローラの項目の親の id。 |
| `menuVar` | `menu` | ビュー変数の名前。 |
| `autoLoad` | `true` | `startup()` でログイン中のユーザーのメニューを作ります。[他の ARO のメニュー](#menus-for-other-aros)を参照してください。 |
| `cacheConfig` | `menu_component` | キャッシュの設定。存在しない場合は File エンジンで作成します。 |
| `cacheKey` | `menu_storage` | キャッシュのキー。 |
| `cacheTime` | `+1 day` | コンポーネントが作成するキャッシュ設定の有効期間。 |

アクションは、コントローラの public メソッドのうち、`Controller` のメソッド (`beforeFilter()`、
`render()` などのコールバック)、`_` で始まるメソッド、除外するアクションを除いたものです。

<a id="controller-options-menuoptions"></a>

## コントローラのオプション ($menuOptions)

コントローラで `$menuOptions` を設定できます。

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

| オプション | 説明 |
| --- | --- |
| `exclude` | メニュー項目にしないアクション。`excludeActions` とそのプレフィックス付きのもの (`admin_view`、`admin_edit` など) に追加されます。`['*']` でコントローラ全体を除外します。 |
| `parent` | コントローラの項目の親の id。たとえば別のコントローラの id (`posts`)。 |
| `alias` | アクションのタイトル (`アクション => タイトル`)。 |
| `controllerButton` | `false` にすると、コントローラの項目を作らずに、アクションを最上位 (または `parent` の下) の項目にします。デフォルトは `true`。 |

id は、コントローラが `controller` (たとえば `posts`、`blogPosts`)、アクションが `controller-action`
(たとえば `posts-add`、`posts-adminIndex`) で、プラグインのコントローラでは先頭にプラグイン名が付きます
(`blog-posts-add`)。

<a id="adding-menu-items"></a>

## メニュー項目の追加

`startup()` でメニューが作られる前に、`beforeFilter()` で他の項目を追加できます。

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

| キー | 説明 |
| --- | --- |
| `title` | タイトル。デフォルトはアクション名を人が読める形にしたもの。 |
| `url` | URL の配列は、コントローラのアクションと同じく ACL で確認します。文字列の URL (または URL なし) は常に表示します。 |
| `id` | 一意の id。デフォルトは `controller-action`。 |
| `parent` | 親の項目の id。 |
| `weight` | weight の小さい項目が先に表示されます。同じ weight の項目は追加した順のままです。 |

<a id="prefixes-and-plugins"></a>

## プレフィックスとプラグイン

`Routing.prefixes` のルーティングプレフィックスが付いたアクション (たとえば `admin_index`) は、URL に
プレフィックスが付き (`'admin' => true`)、それ以外のアクションは `'admin' => false` になります。
コントローラの項目は、プレフィックス付きの index (たとえば `admin_index`) があればそれにリンクします。

プラグインのコントローラを含めるには `plugins` を設定します。ACO のパスにはプラグイン名が入り
(`controllers/Blog/Posts/add`)、URL には `plugin` キーが入ります。

<a id="caching"></a>

## キャッシュ

コントローラの読み取りは時間がかかるので、コンポーネントは次のものをキャッシュします。

- すべてのコントローラのメニュー項目 (`cacheKey`)
- ARO ごとのメニュー (`<Aro><id>_<cacheKey>`、たとえば `User1_menu_storage`)

権限、コントローラ、`$menuOptions` を変えたあとは、キャッシュを削除してください。

```php
$this->Menu->clearCache();
```

`clearCache()` はコンポーネントのキャッシュ設定全体を削除するので、専用のキャッシュ設定
(デフォルトの `menu_component`) を使ってください。開発中は、`duration` を短くした設定を自分で
定義しておくと便利です。

```php
Cache::config('menu_component', ['engine' => 'File', 'duration' => '+1 minute', 'prefix' => 'acl_menu_']);
```

キャッシュにない項目を `addMenu()` で追加すると、メニューは作り直されます。

<a id="menus-for-other-aros"></a>

## 他の ARO のメニュー

`autoLoad` を false にして、メニューを自分で作ります。たとえばグループのメニューを作る場合です。
コンポーネントが `beforeRender()` でビュー変数をセットする前、たとえば `beforeFilter()` で作ってください。

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

`constructMenu()` は、`AclComponent::check()` と同じく、ARO のエイリアスまたは識別用の配列を受け取ります。

<a id="migrating-from-the-cakephp-1-3-version"></a>

## CakePHP 1.3 版からの移行

このパッケージは、CakePHP 1.3 のコンポーネント `markstory/cakephp_menu_component`
(`app/plugins/acl_menu` にインストールするもの) を移植したものです。コンポーネントは引き続き
`AclMenu.Menu` で、設定とメソッドの名前も同じです。変更点は次のとおりです。

- ACO のパスは、`ActionsAuthorize` や AclExtras と同じく CamelCase のコントローラ名を使います:
  `controllers/posts/add` ではなく `controllers/Posts/add`。
- ルーティングプレフィックスは `Routing.admin` ではなく `Routing.prefixes` から読み込みます。
- ログイン中のユーザーの ARO は `[userModel => ユーザーデータ]` です (`userModel` のデフォルトは `User`)。
- メニューの URL には `plugin` キーと各ルーティングプレフィックスが入ります。
- プレフィックス付きのアクションのメニュー id は、CakePHP 2 の `Inflector::variable()` と同じく
  キャメルケースになります (`posts-adminIndex`)。

<a id="origin"></a>

## 由来

このパッケージは、Mark Story による CakePHP 1.3 用の ACL メニューコンポーネント
[markstory/cakephp_menu_component](https://github.com/markstory/cakephp_menu_component) の
メンテナンスされたフォークで、CakePHP 2 に移植したものです。
[pieceofcake2](https://github.com/pieceofcake2) が、モダンな PHP 上の CakePHP 2 向けにメンテナンスしています。
オリジナルからの変更点は
[フォーク以降のコミット履歴](https://github.com/pieceofcake2/acl_menu/compare/9887861...1.x)
にあります。
