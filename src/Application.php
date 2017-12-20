<?php

namespace RFM;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcher;
use RFM\Repository\StorageInterface;
use RFM\API\ApiInterface;
use RFM\Event\Api as ApiEvent;
use function RFM\config;
use function RFM\logger;
use function RFM\request;

// path to "application" folder
defined('FM_APP_PATH') or define('FM_APP_PATH', dirname(__FILE__));
// path to PHP connector root folder
defined('FM_ROOT_PATH') or define('FM_ROOT_PATH', dirname(dirname(__FILE__)));
// path to PHP connector root folder
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

class Application extends Container {
    /**
     * Active API instance.
     *
     * @var ApiInterface
     */
    public $api;

    /**
     * @var StorageInterface[]
     */
    private $storageRegistry = [];

    /**
     * The base path of the application installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * All of the loaded configuration files.
     *
     * @var array
     */
    protected $loadedConfigurations = [];

    /**
     * Application constructor.
     *
     * @param string|null $basePath
     */
    public function __construct($basePath = null)
    {
        if (is_null($basePath)) {
            $basePath = realpath(__DIR__);
        }

        $this->basePath = $basePath;

        $this->bootstrapContainer();
        $this->registerConfigBindings();
        $this->registerLoggerBindings();
        $this->registerRequestBindings();
        $this->registerDispatcherBindings();

        if (function_exists('fm_authenticate')) {
            $authenticated = fm_authenticate();

            if ($authenticated !== true) {
                $data = is_array($authenticated) ? $authenticated : [];
                app()->error('AUTHORIZATION_REQUIRED', [], $data);
            }
        }
    }

    /**
     * Add storage to the collection.
     *
     * @param StorageInterface $storage
     */
    public function setStorage(StorageInterface $storage)
    {
        $name = $storage->getName();

        $this->storageRegistry[$name] = $storage;
    }

    /**
     * Get storage from the collection by name.
     *
     * @param $name
     * @return StorageInterface
     * @throws \Exception
     */
    public function getStorage($name)
    {
        if(!isset($this->storageRegistry[$name])) {
            throw new \Exception("Storage with name \"{$name}\" is not set.");
        }

        return $this->storageRegistry[$name];
    }

    /**
     * Bootstrap the application container.
     *
     * @return void
     */
    protected function bootstrapContainer()
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance('RFM\Application', $this);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerRequestBindings()
    {
        $this->singleton('request', function () {
            return Request::createFromGlobals();
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerLoggerBindings()
    {
        $this->singleton('logger', function () {
            return new Logger();
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerConfigBindings()
    {
        $this->singleton('config', function () {
            return new Repository();
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerDispatcherBindings()
    {
        $this->singleton('dispatcher', function () {
            return new EventDispatcher();
        });
    }

    /**
     * Register events listeners.
     *
     * @return void
     */
    public function registerEventsListeners()
    {
        dispatcher()->addListener(ApiEvent\AfterFolderReadEvent::NAME, 'fm_event_api_after_folder_read');
        dispatcher()->addListener(ApiEvent\AfterFolderSeekEvent::NAME, 'fm_event_api_after_folder_seek');
        dispatcher()->addListener(ApiEvent\AfterFolderCreateEvent::NAME, 'fm_event_api_after_folder_create');
        dispatcher()->addListener(ApiEvent\AfterFileUploadEvent::NAME, 'fm_event_api_after_file_upload');
        dispatcher()->addListener(ApiEvent\AfterFileExtractEvent::NAME, 'fm_event_api_after_file_extract');
        dispatcher()->addListener(ApiEvent\AfterItemRenameEvent::NAME, 'fm_event_api_after_item_rename');
        dispatcher()->addListener(ApiEvent\AfterItemCopyEvent::NAME, 'fm_event_api_after_item_copy');
        dispatcher()->addListener(ApiEvent\AfterItemMoveEvent::NAME, 'fm_event_api_after_item_move');
        dispatcher()->addListener(ApiEvent\AfterItemDeleteEvent::NAME, 'fm_event_api_after_item_delete');
        dispatcher()->addListener(ApiEvent\AfterItemDownloadEvent::NAME, 'fm_event_api_after_item_download');
    }

    /**
     * Load a configuration file into the application.
     *
     * @param string $name
     * @param array $options
     * @return void
     */
    public function configure($name, $options = [])
    {
        if (isset($this->loadedConfigurations[$name])) {
            return;
        }

        $this->loadedConfigurations[$name] = true;

        $path = $this->getConfigurationPath($name);

        if ($path) {
            $config = $this->mergeConfigs(require $path, $options);
            $this->make('config')->set($name, $config);

            // update logger configuration
            if (config("{$name}.logger.enabled") === true) {
                logger()->enabled = true;
            }
            if (is_string(config("{$name}.logger.file"))) {
                logger()->file = config("{$name}.logger.file");
            }
        }
    }

    /**
     * Get the path to the given configuration file.
     *
     * @param string $name
     * @return string
     */
    public function getConfigurationPath($name)
    {
        return $this->basePath() . DS . 'config' . DS . "config.{$name}.php";
    }

    /**
     * Get the base path for the application.
     *
     * @param  string|null  $path
     * @return string
     */
    public function basePath($path = null)
    {
        if (isset($this->basePath)) {
            return $this->basePath.($path ? '/'.$path : $path);
        }

        $this->basePath = realpath(getcwd().'/../');

        return $this->basePath($path);
    }

    /**
     * Invokes API action based on request params and returns response
     *
     * @throws \Exception
     */
    public function run()
    {
        if (count($this->storageRegistry) === 0) {
            throw new \Exception("No storage has been set.");
        }

        if (!($this->api instanceof ApiInterface)) {
            throw new \Exception("API has not been set.");
        }

        $response = null;
        $mode = request()->get('mode');

        if (empty($mode)) {
            $this->error('MODE_ERROR');
        }

        if (request()->isMethod('GET')) {
            switch($mode) {
                case 'initiate':
                    $response = $this->api->actionInitiate();
                    break;

                case 'getinfo':
                    if(request()->get('path')) {
                        $response = $this->api->actionGetInfo();
                    }
                    break;

                case 'readfolder':
                    if(request()->get('path')) {
                        $response = $this->api->actionReadFolder();
                    }
                    break;

                case 'seekfolder':
                    if(request()->get('path') && request()->get('string')) {
                        $response = $this->api->actionSeekFolder();
                    }
                    break;

                case 'rename':
                    if(request()->get('old') && request()->get('new')) {
                        $response = $this->api->actionRename();
                    }
                    break;

                case 'copy':
                    if(request()->get('source') && request()->get('target')) {
                        $response = $this->api->actionCopy();
                    }
                    break;

                case 'move':
                    if(request()->get('old') && request()->get('new')) {
                        $response = $this->api->actionMove();
                    }
                    break;

                case 'delete':
                    if(request()->get('path')) {
                        $response = $this->api->actionDelete();
                    }
                    break;

                case 'addfolder':
                    if(request()->get('path') && request()->get('name')) {
                        $response = $this->api->actionAddFolder();
                    }
                    break;

                case 'download':
                    if(request()->get('path')) {
                        $response = $this->api->actionDownload();
                    }
                    break;

                case 'getimage':
                    if(request()->get('path')) {
                        $thumbnail = isset($_GET['thumbnail']);
                        $this->api->actionGetImage($thumbnail);
                    }
                    break;

                case 'readfile':
                    if(request()->get('path')) {
                        $this->api->actionReadFile();
                    }
                    break;

                case 'summarize':
                    $response = $this->api->actionSummarize();
                    break;
            }
        }

        if (request()->isMethod('POST')) {
            switch($mode) {
                case 'upload':
                    if(request()->get('path')) {
                        $response = $this->api->actionUpload();
                    }
                    break;

                case 'savefile':
                    if(request()->get('path') && request()->get('content')) {
                        $response = $this->api->actionSaveFile();
                    }
                    break;

                case 'extract':
                    if(request()->get('source') && request()->get('target')) {
                        $response = $this->api->actionExtract();
                    }
                    break;
            }
        }

        if (is_null($response)) {
            $this->error('INVALID_ACTION');
        }

        echo json_encode([
            'data' => $response,
        ]);
        exit;
    }

    /**
     * Echo error message and terminate the application
     *
     * @param string $label
     * @param array $arguments
     * @param array $meta
     */
    public function error($label, $arguments = [], $meta = [])
    {
        $meta['arguments'] = $arguments;
        $message = 'Error code: ' . $label . ', meta: ' . json_encode($meta);
        logger()->log($message);

        $error_object = [
            'id' => 'server',
            'code' => '500',
            'title' => $label,
            'meta' => $meta,
        ];

        echo json_encode([
            'errors' => [$error_object],
        ]);

        header('Content-Type: application/json');
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }

    /**
     * Merges two or more arrays into one recursively.
     * If each array has an element with the same string key value, the latter will overwrite the former.
     * Recursive merging will be conducted if both arrays have an element of array type and are having the same key.
     * For array elements which are entirely integer-keyed, latter will straight overwrite the former.
     * For integer-keyed elements, the elements from the latter array will be appended to the former array.
     *
     * @param array $a array to be merged to
     * @param array $b array to be merged from. You can specify additional
     * arrays via third argument, fourth argument etc.
     * @return array the merged array (the original arrays are not changed.)
     */
    public function mergeConfigs($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            $next = array_shift($args);
            foreach ($next as $k => $v) {
                if (is_int($k)) {
                    if (isset($res[$k])) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    // check if array keys is sequential to consider its as indexed array
                    // http://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
                    if (array_keys($res[$k]) === range(0, count($res[$k]) - 1)) {
                        $res[$k] = $v;
                    } else {
                        $res[$k] = self::mergeConfigs($res[$k], $v);
                    }
                } else {
                    $res[$k] = $v;
                }
            }
        }
        return $res;
    }

    /**
     * Check if this is running under PHP for Windows.
     *
     * @return bool
     */
    public function php_os_is_windows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version()
    {
        return 'RichFilemanager PHP connector v1.2.0';
    }
}