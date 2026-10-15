<?php

App::uses('Controller', 'Controller');
App::uses('ComponentCollection', 'Controller');
App::uses('AclComponent', 'Controller/Component');
App::uses('MenuComponent', 'AclMenu.Controller/Component');

class MenuTestPostsController extends Controller
{
    public $uses = [];

    public function index(): void
    {
    }

    public function add(): void
    {
    }

    public function view(): void
    {
    }

    public function admin_index(): void
    {
    }

    public function admin_add(): void
    {
    }

    public function admin_edit(): void
    {
    }

    protected function _hidden(): void
    {
    }
}

class MenuTestCommentsController extends Controller
{
    public $uses = [];

    public $menuOptions = [
        'parent' => 'menuTestPosts',
        'exclude' => ['delete'],
        'alias' => ['index' => 'All comments'],
    ];

    public function index(): void
    {
    }

    public function delete(): void
    {
    }
}

class MenuTestSecretsController extends Controller
{
    public $uses = [];

    public $menuOptions = ['exclude' => ['*']];

    public function index(): void
    {
    }
}

class MenuTestPagesController extends Controller
{
    public $uses = [];

    public $menuOptions = ['controllerButton' => false];

    public function about(): void
    {
    }

    public function contact(): void
    {
    }
}

class MenuTestBlogArticlesController extends Controller
{
    public $uses = [];

    public function index(): void
    {
    }
}

class TestMenuComponent extends MenuComponent
{
    public array $controllers = [];

    public function getControllers(): array
    {
        return $this->controllers;
    }

    public function formatMenu(array $menu): array
    {
        return $this->_formatMenu($menu);
    }

    public function mergeMenuCache(array $cached): array
    {
        return $this->_mergeMenuCache($cached);
    }

    public function aco(array $item): ?string
    {
        return $this->_aco($item);
    }
}

class MenuComponentTest extends CakeTestCase
{
    protected TestMenuComponent $Menu;

    protected Controller $Controller;

    /**
     * ACO paths checked with ACL.
     *
     * @var array<string>
     */
    protected array $checked = [];

    /**
     * ACO paths denied by ACL.
     *
     * @var array<string>
     */
    protected array $denied = [];

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Routing.prefixes', ['admin']);
        $this->checked = $this->denied = [];
        $this->Menu = $this->createMenu();
    }

    public function tearDown(): void
    {
        $this->Menu->clearCache();
        Cache::drop('menu_component');
        Configure::delete('Routing.prefixes');
        CakeSession::delete('Auth');
        parent::tearDown();
    }

    protected function createMenu(array $settings = []): TestMenuComponent
    {
        $this->Controller = new Controller(new CakeRequest('/', false), new CakeResponse());
        $collection = new ComponentCollection();
        $collection->init($this->Controller);
        $menu = new TestMenuComponent($collection, $settings);
        $menu->controllers = ['' => [
            'AppController',
            'MenuTestPostsController',
            'MenuTestCommentsController',
            'MenuTestSecretsController',
            'MenuTestPagesController',
        ]];

        $acl = $this->getMockBuilder(AclComponent::class)
            ->setConstructorArgs([$collection])
            ->onlyMethods(['check'])
            ->getMock();
        $acl->method('check')->willReturnCallback(function ($aro, $aco) {
            $this->checked[] = $aco;

            return !in_array($aco, $this->denied, true);
        });
        $menu->Acl = $acl;
        $menu->initialize($this->Controller);

        return $menu;
    }

    protected function ids(array $items): array
    {
        return array_column($items, 'id');
    }

    public function testSettings(): void
    {
        $menu = $this->createMenu(['cacheKey' => 'my_key', 'aclPath' => 'actions/', 'defaultMenuParent' => 'root', 'userModel' => 'Member']);

        $this->assertSame('my_key', $menu->cacheKey);
        $this->assertSame('actions/', $menu->aclPath);
        $this->assertSame('root', $menu->defaultMenuParent);
        $this->assertSame('Member', $menu->userModel);
        $this->assertContains('menu_component', Cache::configured());
    }

    public function testFilterMethods(): void
    {
        $this->Menu->createExclusions();
        $methods = ['add', 'buildFoo', 'view', 'bother', 'beforeFilter', 'render', '_hidden', '__secret'];

        $this->assertSame(['add', 'buildFoo', 'bother'], $this->Menu->filterMethods($methods));
        $this->assertSame(['add', 'bother'], $this->Menu->filterMethods($methods, ['buildFoo']));
    }

    public function testSetOptions(): void
    {
        $options = $this->Menu->setOptions([]);
        $this->assertSame(['admin_view', 'admin_edit', 'admin_delete'], $options['exclude']);
        $this->assertTrue($options['controllerButton']);
        $this->assertNull($options['parent']);

        $this->assertFalse($this->Menu->setOptions(['menuOptions' => ['exclude' => ['*']]]));
    }

    public function testGenerateRawMenus(): void
    {
        $this->Menu->generateRawMenus();
        $items = Hash::combine($this->Menu->rawMenus, '{n}.id', '{n}');

        $this->assertSame([
            'menuTestPosts-index', 'menuTestPosts-add', 'menuTestPosts-adminIndex', 'menuTestPosts-adminAdd', 'menuTestPosts',
            'menuTestComments-index', 'menuTestComments',
            'menuTestPages-about', 'menuTestPages-contact',
        ], array_keys($items));

        $this->assertSame(
            ['plugin' => null, 'controller' => 'menu_test_posts', 'action' => 'add', 'admin' => false],
            $items['menuTestPosts-add']['url'],
        );
        $this->assertSame('menuTestPosts', $items['menuTestPosts-add']['parent']);
        $this->assertSame('Add', $items['menuTestPosts-add']['title']);
        $this->assertTrue($items['menuTestPosts-adminAdd']['url']['admin']);
        $this->assertSame('Admin Add', $items['menuTestPosts-adminAdd']['title']);

        // the controller item uses the prefixed index if there is one
        $this->assertSame(
            ['plugin' => null, 'controller' => 'menu_test_posts', 'action' => 'admin_index', 'admin' => true],
            $items['menuTestPosts']['url'],
        );
        $this->assertSame('Menu Test Posts', $items['menuTestPosts']['title']);

        // menuOptions: parent, alias, exclude, controllerButton
        $this->assertSame('menuTestPosts', $items['menuTestComments']['parent']);
        $this->assertSame('All comments', $items['menuTestComments-index']['title']);
        $this->assertSame('index', $items['menuTestComments']['url']['action']);
        $this->assertNull($items['menuTestPages-about']['parent']);
    }

    public function testGenerateRawMenusWithoutPrefixes(): void
    {
        Configure::delete('Routing.prefixes');
        $this->Menu->generateRawMenus();
        $items = Hash::combine($this->Menu->rawMenus, '{n}.id', '{n}');

        $this->assertArrayHasKey('menuTestPosts-adminEdit', $items);
        $this->assertSame(['plugin' => null, 'controller' => 'menu_test_posts', 'action' => 'index'], $items['menuTestPosts']['url']);
    }

    public function testGenerateRawMenusOfPlugins(): void
    {
        $this->Menu->controllers['Blog'] = ['BlogAppController', 'MenuTestBlogArticlesController'];
        $this->Menu->generateRawMenus();
        $items = Hash::combine($this->Menu->rawMenus, '{n}.id', '{n}');

        $this->assertSame(
            ['plugin' => 'blog', 'controller' => 'menu_test_blog_articles', 'action' => 'index', 'admin' => false],
            $items['blog-menuTestBlogArticles-index']['url'],
        );
        $this->assertSame('controllers/Blog/MenuTestBlogArticles/index', $this->Menu->aco($items['blog-menuTestBlogArticles-index']));
    }

    public function testGetControllers(): void
    {
        $menu = new MenuComponent(new ComponentCollection());
        $controllers = $menu->getControllers();

        $this->assertSame([''], array_keys($controllers));
        $this->assertContains('PagesController', $controllers['']);
    }

    public function testAco(): void
    {
        $this->assertSame('controllers/Posts/add', $this->Menu->aco(['url' => ['controller' => 'posts', 'action' => 'add']]));
        $this->assertSame('controllers/BlogPosts/admin_index', $this->Menu->aco(['url' => ['controller' => 'blog_posts', 'action' => 'admin_index', 'admin' => true]]));
        $this->assertSame('controllers/Posts', $this->Menu->aco(['url' => ['controller' => 'posts']]));
        $this->assertNull($this->Menu->aco(['url' => '/about']));
        $this->assertNull($this->Menu->aco(['url' => null]));
    }

    public function testAddMenu(): void
    {
        $this->Menu->addMenu(['url' => ['controller' => 'posts', 'action' => 'delete']]);
        $this->assertSame([
            'url' => ['controller' => 'posts', 'action' => 'delete'],
            'title' => 'Delete',
            'parent' => null,
            'id' => 'posts-delete',
            'weight' => 0,
        ], $this->Menu->rawMenus[0]);

        $this->Menu->addMenu(['url' => ['controller' => 'posts', 'action' => 'delete'], 'id' => 'funky-id', 'parent' => 'posts']);
        $this->assertSame('funky-id', $this->Menu->rawMenus[1]['id']);
        $this->assertSame('posts', $this->Menu->rawMenus[1]['parent']);
    }

    public function testFormatMenu(): void
    {
        $this->Menu->generateRawMenus();
        $menu = $this->Menu->formatMenu(Hash::combine($this->Menu->rawMenus, '{n}.id', '{n}'));

        $this->assertSame(['menuTestPosts', 'menuTestPages-about', 'menuTestPages-contact'], $this->ids($menu));
        $this->assertSame(
            ['menuTestPosts-index', 'menuTestPosts-add', 'menuTestPosts-adminIndex', 'menuTestPosts-adminAdd', 'menuTestComments'],
            $this->ids($menu[0]['children']),
        );
        $this->assertSame(['menuTestComments-index'], $this->ids($menu[0]['children'][4]['children']));
        $this->assertSame([], $menu[1]['children']);
    }

    public function testFormatMenuSortsByWeight(): void
    {
        $menu = $this->Menu->formatMenu([
            'a' => ['id' => 'a', 'parent' => null, 'weight' => 5],
            'b' => ['id' => 'b', 'parent' => null, 'weight' => 0],
            'c' => ['id' => 'c', 'parent' => null, 'weight' => 0],
            'c1' => ['id' => 'c1', 'parent' => 'c', 'weight' => 2],
            'c2' => ['id' => 'c2', 'parent' => 'c', 'weight' => -1],
        ]);

        $this->assertSame(['b', 'c', 'a'], $this->ids($menu));
        $this->assertSame(['c2', 'c1'], $this->ids($menu[1]['children']));
    }

    public function testConstructMenu(): void
    {
        $this->Menu->constructMenu(['User' => ['id' => 1]]);

        $this->assertCount(7, $this->checked, 'Each ACO is checked once.');
        $this->assertContains('controllers/MenuTestPosts/admin_index', $this->checked);
        $this->assertSame(['menuTestPosts', 'menuTestPages-about', 'menuTestPages-contact'], $this->ids($this->Menu->menu));
        $this->assertIsArray(Cache::read('User1_menu_storage', 'menu_component'));
    }

    public function testConstructMenuWithDeniedActions(): void
    {
        $this->denied = ['controllers/MenuTestPosts/add', 'controllers/MenuTestPages/about'];
        $this->Menu->constructMenu(['User' => ['id' => 1]]);

        $this->assertSame(['menuTestPosts', 'menuTestPages-contact'], $this->ids($this->Menu->menu));
        $this->assertNotContains('menuTestPosts-add', $this->ids($this->Menu->menu[0]['children']));
    }

    public function testConstructMenuDeniedControllerHidesChildren(): void
    {
        $this->denied = ['controllers/MenuTestPosts/admin_index'];
        $this->Menu->constructMenu(['User' => ['id' => 1]]);

        $this->assertSame(['menuTestPages-about', 'menuTestPages-contact'], $this->ids($this->Menu->menu));
    }

    public function testConstructMenuUsesCachePerAro(): void
    {
        $this->Menu->constructMenu(['User' => ['id' => 1]]);
        $this->Menu->writeCache();
        $this->checked = [];

        $menu = $this->createMenu();
        $menu->loadCache();
        $menu->constructMenu(['User' => ['id' => 1]]);
        $this->assertSame([], $this->checked, 'The cached menu of the ARO is used.');
        $this->assertSame($this->Menu->menu, $menu->menu);

        $menu->constructMenu(['User' => ['id' => 2]]);
        $this->assertCount(7, $this->checked);
    }

    public function testAddedMenusAreChecked(): void
    {
        $this->Menu->addMenu(['url' => ['controller' => 'reports', 'action' => 'index'], 'title' => 'Reports', 'weight' => -1]);
        $this->Menu->addMenu(['url' => 'https://example.com/help', 'title' => 'Help', 'id' => 'help', 'weight' => 10]);
        $this->Menu->constructMenu(['User' => ['id' => 1]]);

        $this->assertContains('controllers/Reports/index', $this->checked);
        $this->assertSame(['reports-index', 'menuTestPosts', 'menuTestPages-about', 'menuTestPages-contact', 'help'], $this->ids($this->Menu->menu));
    }

    public function testLoadAndWriteCache(): void
    {
        $this->assertFalse($this->Menu->loadCache());
        $this->Menu->generateRawMenus();
        $this->assertTrue($this->Menu->writeCache());

        $menu = $this->createMenu();
        $this->assertTrue($menu->loadCache());
        $this->assertSame($this->Menu->rawMenus, $menu->rawMenus);
    }

    public function testMergeMenuCacheRebuildsWithNewItems(): void
    {
        $this->Menu->generateRawMenus();
        $cached = $this->Menu->rawMenus;
        $this->Menu->writeCache();
        $this->Menu->constructMenu(['User' => ['id' => 1]]);

        $menu = $this->createMenu();
        $menu->addMenu(['url' => ['controller' => 'reports', 'action' => 'index']]);
        $menu->addMenu(['url' => ['controller' => 'menu_test_posts', 'action' => 'add']]);
        $merged = $menu->mergeMenuCache($cached);
        $this->assertCount(count($cached) + 1, $merged);

        $menu->loadCache();
        $menu->constructMenu(['User' => ['id' => 1]]);
        $this->assertContains('reports-index', $this->ids($menu->menu), 'The menu is rebuilt with the new item.');
    }

    public function testClearCache(): void
    {
        $this->Menu->constructMenu(['User' => ['id' => 1]]);
        $this->Menu->writeCache();
        $this->assertTrue($this->Menu->clearCache());

        $this->assertFalse(Cache::read('menu_storage', 'menu_component'));
        $this->assertFalse(Cache::read('User1_menu_storage', 'menu_component'));
    }

    public function testStartupAndBeforeRender(): void
    {
        CakeSession::write('Auth.User', ['id' => 3, 'username' => 'mariano']);
        $aros = [];
        $this->Menu->Acl = $this->getMockBuilder(AclComponent::class)
            ->setConstructorArgs([new ComponentCollection()])
            ->onlyMethods(['check'])
            ->getMock();
        $this->Menu->Acl->method('check')->willReturnCallback(function ($aro) use (&$aros) {
            $aros[] = $aro;

            return true;
        });
        $this->Menu->startup($this->Controller);
        $this->Menu->beforeRender($this->Controller);

        $this->assertSame(['User' => ['id' => 3, 'username' => 'mariano']], $aros[0]);
        $this->assertSame($this->Menu->menu, $this->Controller->viewVars['menu']);
        $this->assertNotEmpty($this->Controller->viewVars['menu']);
        $this->assertIsArray(Cache::read('menu_storage', 'menu_component'));
    }

    public function testStartupWithoutUser(): void
    {
        $this->Menu->startup($this->Controller);
        $this->Menu->beforeRender($this->Controller);

        $this->assertSame([], $this->checked);
        $this->assertSame([], $this->Controller->viewVars['menu']);
    }

    public function testStartupWithoutAutoLoad(): void
    {
        CakeSession::write('Auth.User', ['id' => 3]);
        $menu = $this->createMenu(['autoLoad' => false, 'menuVar' => 'navigation']);
        $menu->startup($this->Controller);
        $menu->beforeRender($this->Controller);

        $this->assertSame([], $this->checked);
        $this->assertSame([], $this->Controller->viewVars['navigation']);
    }
}
