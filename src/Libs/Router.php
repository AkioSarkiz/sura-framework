<?php
declare(strict_types=1);

namespace Sura\Libs;

use Sura\Exception\SuraException;

/**
 * Class Router
 * @package Sura\Libs
 */
class Router
{
    /**
     * @var array $routes
     */
    private static array $routes = [];

    /**
     * @var string $requestUri
     */
    private static string $requestUri;

    /**
     * @var string $requestMethod
     */
    private static string $requestMethod;

    /**
     * @var string $requestHandler
     */
    private static string $requestHandler;

    /**
     * @var array|null $params
     * @deprecated
     */
    private static ?array $params = [];

    /**
     * @var string[] $placeholders
     */
    private static array $placeholders = [
        ':seg' => '([^\/]+)',
        ':num'  => '([0-9]+)',
        ':any'  => '(.+)'
    ];

    /**
     * @var string $controllerName
     */
    private static string $controllerName;

    /**
     * @var string $actionName
     */
    private static string $actionName;

    /**
     * Router constructor.
     * @param string $uri
     * @param string $method
     */
    public function __construct(string $uri, string $method = 'GET')
    {
        self::$requestUri = $uri;
        self::$requestMethod = $method;
    }

    /**
     * Factory method construct Router from global vars.
     * @return Router
     */
    public static function fromGlobals(): Router
    {
        $config = Settings::load();
        $requests = Request::getRequest();
        $server = $requests->server;
        $method = $requests->getMethod();
        if (isset($server['REQUEST_URI'])) {
            $uri = $server['REQUEST_URI'];
        }elseif(!empty($config['home_url'])){
            $uri = $config['home_url'];
        }else{
            $uri = '';
            echo 'error: non url';
        }
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);
        return new static($uri, $method);
    }
    /**
     * Current .
     * @return array
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Current processed URI.
     * @return string
     */
    public static function getRequestUri(): string
    {
        return self::$requestUri; // ?: '/';
    }

    /**
     * Request method.
     * @return string
     */
    public static function getRequestMethod(): string
    {
        return self::$requestMethod;
    }

    /**
     * Get Request handler.
     * @return string
     */
    public static function getRequestHandler(): string
    {
        return self::$requestHandler;
    }

    /**
     * Set Request handler.
     * @param $handler string|callable
     */
    public function setRequestHandler(string|callable $handler)
    {
        self::$requestHandler = $handler;
    }

    /**
     * Request wildcard params.
     * @return array
     * @deprecated
     */
    public static function getParams(): array
    {
        //TODO
        return self::$params; //old
    }

    /**
     * Request params.
     * @return string
     */
    public static function getControllerName() : string
    {
        return self::$controllerName;
    }

    /**
     * Request params.
     * @return string
     */
    public static function getActionName() : string
    {
        return self::$actionName;
    }

    /**
     * Add route rule.
     *
     * @param string|array $route A URI route string or array
     * @param callable|string|null $handler Any callable or string with controller classname and action method like "ControllerClass@actionMethod"
     * @return Router
     */
    public function add(array|string $route, callable|null|string $handler = null) : Router
    {
        if ($handler !== null && !is_array($route)) {
            $route = array($route => $handler);
        }
        self::$routes = array_merge(self::$routes, $route);
        return $this;
    }

    /**
     * Process requested URI.
     * @return bool
     */
    public function isFound() : bool
    {
        $uri = self::getRequestUri();

        /**
         *  if URI equals to route
         */
        if (isset(self::$routes[$uri])) {
            self::$requestHandler = self::$routes[$uri];
            return true;
        }

        $find    = array_keys(self::$placeholders);
        $replace = array_values(self::$placeholders);
        foreach (self::$routes as $route => $handler) {
            /**
             *  Replace wildcards by regex
             */
            if (str_contains($route, ':')) {
                $route = str_replace($find, $replace, $route);
            }
            /**
             *  Route rule matched
             */
            if (preg_match('#^' . $route . '$#', $uri, $matches)) {
                self::$requestHandler = $handler;
                self::$params = array_slice($matches, 1);
                return true;
            }
        }
        return false;
    }

    /**
     * Execute Request Handler.
     * Запуск соответствующего действия/экшена/метода контроллера
     *
     * @param callable|null|string $handler
     * @param array $params
     * @return mixed
     */
    public function executeHandler(callable|null|string $handler = null, array $params = array()): mixed
    {
        if ($handler === null) {
            throw SuraException::Error('Request handler not setted out. Please check '.__CLASS__.'::isFound() first');
        }

        // execute action in callable
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }
        // execute action in controllers
        if (strpos($handler, '@')) {
            $ca = explode('@', $handler);

            $controller_name = self::$controllerName = $ca['0'];
            $action = self::$actionName = $ca['1'];

            if (class_exists('\\App\\Modules\\'.$controller_name)) {
                if (!method_exists('\\App\\Modules\\'.$controller_name, $action)) {
                    throw SuraException::Error("Method '\\App\\Modules\\{$controller_name}::{$action}()' not found");
                }

                $class = 'App\Modules\\'.$controller_name;
                $foo = new $class();
                return call_user_func_array(array($foo, $action), array());
//                    return call_user_func_array(array($foo, $action), $params);
            }

            throw SuraException::Error("Class '{$controller_name}' not found");
        }
        throw SuraException::Error("Execute handler error");
    }
}