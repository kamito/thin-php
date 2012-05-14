<?php

/**
 * ===== Thin
 */
class Thin {

    /**
     * 設定インスタンス
     * @type {ThinConfig}
     */
    public $Conf = null;
    public $C = null;

    /**
     * データインスタンス
     * @type {ThinData}
     */
    public $Request = null;
    public $R = null;

    /**
     * ルーターインスタンス
     * @type {ThinRouter}
     */
    public $Router = null;

    /**
     * ルートインスタンス
     * @type {ThinRoute}
     */
    public $Route = null;

    /**
     * すでにrun()が実行されているかどうかのフラグ
     * @type {boolean}
     */
    public $isRunned = false;

    /**
     * 自身が保有するインスタンスの一覧
     * シングルトンインスタンスの格納を行う配列
     * @enum {Object}
     */
    static public $instances = array();

    /**
     * @construtor
     * @param $config {ThinConfig|array} 初期設定
     */
    function __construct($config=null) {
        // Config
        $configCls = (isset($config['config.class'])) ? $config['class.config'] : 'ThinConfig';
        $this->Conf = $this->C = $this->getInstance($configCls);
        $this->Conf->setConfig($config);
        // Data
        $this->Request = $this->R = $this->getInstance($this->C->get('class.request', 'ThinRequest'));
        // Router
        $this->Router = $this->getInstance($this->C->get('class.router', 'ThinRouter'));
    }

    /**
     * フォームデータとクエリデータを取得する
     * $keyが指定されていなかった場合は、全データを返す
     * @param $opt_key {String} キー
     * @param $opt_val {Any} 値
     */
    function data($opt_key=null, $opt_val=null) {
        return $this->Data->data($opt_key, $opt_val);
    }

    /**
     * 設定を取得またはセットする
     * $keyが指定されていなかった場合は、設定を配列で返す
     * @param $opt_key {String} キー
     * @param $opt_val {Any} 取得のときに値が見つからなかったときのデータ
     * @param $opt_setVal {Any} 強制的に値をセットするときのデータ
     */
    function conf($opt_key=null, $opt_val=null, $opt_setVal=null) {
        if (isset($opt_setVal)) {
            return $this->Conf->set($opt_key, $opt_setVal);
        } else if (isset($opt_key)) {
            $opt_val = (isset($opt_val)) ? $opt_val : null;
            return $this->Conf->get($opt_key, $opt_val);
        } else {
            return $this->Conf->config_;
        }
    }

    /**
     * ルーティング設定を取得またはセットする
     */
    function addRoute() {
        $router = $this->R;
    }

    /**
     * インスタンスを取得
     */
    public function getInstance($klass) {
        return $klass::getInstance($this);
    }


    /**
     * Run the Application
     */
    public function run($path=null, $config=null) {
        if ($this->C->get('config.once_run') && $this->isRunned) {
            // Once run
        } else {
            if (!isset($path)) {
                $path = $this->R->getPath();
            }
            $this->C->setConfig($config);
            $this->Route = $this->Router->load($path);

            $this->isRunned = true;
            if ($this->C->get('config.run_and_exit')) {
                exit(0);
            }
        }
    }
}


/**
 * すべてのクラスのベースクラス
 */
class ThinBase {
    /**
     * 引数の値をvar_dumpで出力する
     * @param $obj {Any} 出力する値
     */
    public function dump($obj) {
        ThinLogger::var_dump($obj);
    }
}


/**
 * シングルトンクラスのベース
 */
class ThinSingleton extends ThinBase {

    protected $thin = null;

    /**
     * @constructor
     * すでにインスタンスが生成されていたらエラーにする
     */
    final private function __construct($thin) {
        if (!isset($thin) || isset($thin->instances[get_called_class()])) {
            throw new Exception("Duplicate Instance: " . get_called_class());
        }
        $this->thin = $thin;
        static::initialize();
    }

    /**
     * 初期化
     */
    protected function initialize() {
        // Override from Childlen
    }

    /**
     * インスタンスを取得
     */
    final public static function getInstance($thin) {
        $klass = get_called_class();
        if (!isset($thin->instances[$klass])) {
            $thin->instances[$klass] = new static($thin);
        }
        return $thin->instances[$klass];
    }

    /**
     * インスタンスのクローン
     * シングルトンなので不可
     */
    final private function __clone() {
        throw new Exception("Can't clone. This is a Singleton Instance");
    }
}


/**
 * 設定クラス
 */
class ThinConfig extends ThinSingleton {

    public $LOGGER_LEVEL = 0;

    /**
     * @enum {string}
     */
    public $config_ = array();

    /**
     * @enum {string}
     */
    public $defaultConfig = array(
        'thin' => array(
            'once_run' => true,
            // @TODO: デフォルトでtrueがいいか、falseがいいかは要検討
            'run_and_exit' => true,
        ),
        'class' => array(
            'config' => 'ThinConfig',
            'request' => 'ThinRequest',
            'router' => 'ThinRouter',
            'route' => 'ThinRoute',
        ),
        'url' => array(
            'base_url' => '/',
            'pretty' => false,
            'path_query' => 'do',
            'path_val' => 'request_uri',
        ),
        'path' => array(
            
        ),
        'routes' => array(
            '/' => null,
        ),
    );

    /**
     * 初期化
     */
    protected function initialize() {
        $this->config_ = (isset($this->config_)) ? $this->config_ : array();
        $this->defaultConfig =ThinUtil::mergeHash($this->defaultConfig, array(
            'base' => dirname(__FILE__),
        ));
        $this->config_ = ThinUtil::mergeHash($this->config_, $this->defaultConfig);
    }

    /**
     * 設定を取得する
     */
    public function get($key, $opt_val=null) {
        return ThinUtil::recursiveArraySearch($this->config_, $key);
    }

    /**
     * 設定をセットする
     * 配列で渡すことで既存の設定とマージして保持する
     * @param $config {array} 設定
     */
    public function setConfig($config=null) {
        $this->config_ = (isset($this->config_)) ? $this->config_ : array();
        $this->config_ = ThinUtil::mergeHash($this->config_, $config);
        return $this->config_;
    }
}


/**
 * データクラス
 * フォームデータなどを扱う
 */
class ThinRequest extends ThinSingleton {
    /**
     * POSTデータ
     * @type {array}
     */
    private $postData_ = array();

    /**
     * GETデータ
     * @type {array}
     */
    private $getData_ = array();

    /**
     * ALLデータ
     * POSTデータとGETデータをマージした全データ
     * @type {array}
     */
    private $allData_ = array();

    /**
     * URLパス
     * @type {string}
     */
    public $path = null;

    /**
     * 初期化
     */
    protected function initialize() {
        $this->postData_ = (empty($_POST)) ? array() : $_POST;
        $this->getData_ = (empty($_GET)) ? array() : $_GET;
        $this->allData_ = $this->initData_();
        $this->path = null;
    }

    /**
     * $_POSTデータを取得する
     * $keyが指定されていなかった場合は、全データを返す
     * @param $opt_key {String} キー ("."区切りの文字列で指定する)
     * @param $opt_val {Any} 値
     * @return {array|string|integer} キーにマッチした値
     */
    public function formData($opt_key=null, $opt_val=null) {
        return ThinUtil::recursiveArraySearch($this->postData_, $opt_key);
    }

    /**
     * $_GETデータを取得する
     * $keyが指定されていなかった場合は、全データを返す
     * @param $opt_key {String} キー ("."区切りの文字列で指定する)
     * @param $opt_val {Any} 値
     * @return {array|string|integer} キーにマッチした値
     */
    public function queryData($opt_key=null, $opt_val=null) {
        return ThinUtil::recursiveArraySearch($this->getData_, $opt_key);
    }

    /**
     * マージされた全てのデータを取得する
     * $keyが指定されていなかった場合は、全データを返す
     * @param $opt_key {String} キー ("."区切りの文字列で指定する)
     * @param $opt_val {Any} 値
     * @return {array|string|integer} キーにマッチした値
     */
    public function data($opt_key=null, $opt_val=null) {
        return ThinUtil::recursiveArraySearch($this->allData_, $opt_key);
    }

    /**
     * $_ENVから値を取得する
     * $keyが指定されていなかった場合は、nullを返す
     * @param $opt_key {String} キー ("."区切りの文字列で指定する)
     * @param $opt_val {Any} 値
     * @return {array|string|integer} キーにマッチした値
     */
    public function env($opt_key=null, $opt_val=null) {
        return ThinUtil::recursiveArraySearch($_ENV, $opt_key, true);
    }

    /**
     * $_SERVERから値を取得する
     * $keyが指定されていなかった場合は、nullを返す
     * @param $opt_key {String} キー ("."区切りの文字列で指定する)
     * @return {array|string|integer} キーにマッチした値
     */
    public function server($opt_key=null) {
        $opt_key = (isset($opt_key)) ? strtoupper($opt_key) : null;
        return ThinUtil::recursiveArraySearch($_SERVER, $opt_key, true);
    }

    /**
     * URLパスを取得する
     * @return {string} URLパス
     */
    public function getPath() {
        if (!isset($this->path)) {
            $config = $this->thin->C;
            $urlPretty = $config->get('url.pretty');
            if ($urlPretty) {
                $path = $this->server($config->get('url.path_val'));
                $this->path = str_replace($config->get('path.base'), '', $path);
            } else {
                $this->path = $this->data($config->get('url.path_query'));
            }

            if (!isset($this->path)) {
                $this->path = '/';
            }
        }
        return $this->path;
    }


    // private --------------------

    /**
     * データをマージする
     * @return {Hash} マージしたデータ
     */
    private function initData_() {
        $data = ThinUtil::mergeHash($this->getData_, $this->postData_);
        return $data;
    }
}


/**
 * ルーティングクラス
 */
class ThinRouter extends ThinSingleton {

    /**
     * 設定インスタンス
     * @tyoe {ThinConfig}
     */
    private $config = null;

    /**
     * ルーティング設定
     * @enum {ThinRoute}
     */
    private $routes = array();

    /**
     * 初期化
     */
    protected function initialize() {
        $this->config = $this->thin->C;
        $this->request = $this->thin->R;
        $this->routes = $this->config->get('routes', array());
    }

    /**
     * ルーティング設定を追加する
     */
    public function addRoute() {

    }

    /**
     * パスを元に読み込むべきファイルを読み込む
     * @param $path {string} URLパス
     * @param {ThinResponse} Responseインスタンス
     */
    public function load($path=null) {
        $route = $this->getRoute($path);
        return $route;
    }

    /**
     * URLに対応するRouteインスタンスを取得する
     * @param $path {string} URLパス
     * @param {ThinRoute} Routeインスタンス
     */
    public function getRoute($path) {
        $route = null;
        $routes = $this->routes;
        foreach ($routes as $url => $routeConf) {
            if ($url == $path || preg_match($url, $path)) {
                $klass = $this->config->get('class.route');
                $route = new $klass($url, $routeConf);
                break;
            }
        }
        return $route;
    }
}


/**
 * ルーティングクラス
 */
class ThinRoute extends ThinBase {

    /**
     * URL
     * @param {string}
     */
    public $url = null;

    /**
     * ルーティング設定
     * @param {array}
     */
    public $config = null;

    /**
     * @constructor
     */
    function __construct($url, $config=null) {
        $this->url = $url;
        $this->config = $config;
    }
};




/**
 * ユーティリティクラス
 */
class ThinUtil extends ThinBase {

    /**
     * データをマージする
     * @param $arr1 {hash} マージ対象のハッシュ
     * @param $arr2 {hash} マージするハッシュ
     * @return {hash} マージしたデータ
     */
    static public function mergeHash($arr1, $arr2) {
        $a1 = $arr1;
        $a2 = $arr2;
        if (is_array($a1)) {
            if (is_array($a2)) {
                foreach ($a2 as $k => $v) {
                    if (isset($a1[$k]) && is_array($v) && is_array($a1[$k])) {
                        $a1[$k] = ThinUtil::mergeHash($a1[$k], $v);
                    } else {
                        $a1[$k] = $v;
                    }
                }
            }
        } elseif (!is_array($a1) && (strlen($a1) == 0 || $a1 == 0)) {
            $a1 = $a2;
        }
        return $a1;
    }

    /**
     * キーを指定して配列の値を取得する
     * キーは"."区切りの文字列で指定することができる
     * @param $data {hash} 検索対象の配列
     * @param $key {string} キー
     * @param $opt_withoutAll {boolean} キーがセットされていないときにnullを返す
     * @return {Any} キーに一致した値
     */
    static public function recursiveArraySearch($data, $key=null, $opt_withoutAll=false) {
        $dat = $data;
        if (isset($key)) {
            $keys = explode('.', $key);
            $keylen = count($keys);
            foreach ($keys as $i => $k) {
                if (isset($dat) && isset($dat[$k])
                    && ($i == ($keylen - 1) || (is_array($dat[$k])))) {
                    $dat = $dat[$k];
                } else {
                    $dat = null;
                }
            }
        } else if ($opt_withoutAll === true) {
            $dat = null;
        }
        return $dat;
    }

    /**
     * $instanceが$klassのインスタンスであるかどうかを調べる
     * @param @instance {AnyObject} 対象のインスタンス
     * @param @klass {AnyClass} 対象のクラス
     * @return {boolean} 一致したらtrueを返し、違ったらfalseを返す
     */
    static public function is_a($instance, $klass) {
        return is_a($instance, $klass);
    }
}


/**
 * ロガー
 */
class ThinLogger extends ThinSingleton {

    /**
     * 引数の値をvar_dumpで出力する
     * @param $obj {Any} 出力する値
     */
    public function dump($obj) {
        ThinLogger::var_dump($obj);
    }

    /**
     * 引数の値をvar_dumpで出力する
     * @param $obj {Any} 出力する値
     */
    static public function var_dump($obj) {
        echo '<pre>';
        var_dump($obj);
        echo '</pre>';
    }
}
