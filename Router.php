<?php
/**
 * Digital Sehat Ghar - Router
 * ==========================
 * Handles URL routing, middleware, and controller dispatch
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

class Router
{
    /**
     * Array of registered routes
     *
     * @var array
     */
    private $routes = [];

    /**
     * Current HTTP method
     *
     * @var string
     */
    private $method = '';

    /**
     * Current URI path
     *
     * @var string
     */
    private $path = '';

    /**
     * Route parameters
     *
     * @var array
     */
    private $params = [];

    /**
     * Middleware stack
     *
     * @var array
     */
    private $middleware = [];

    /**
     * Route middleware groups
     *
     * @var array
     */
    private $middlewareGroups = [
        'web' => [],
        'api' => [],
        'admin' => [],
        'doctor' => [],
        'patient' => []
    ];

    /**
     * Named middleware
     *
     * @var array
     */
    private $namedMiddleware = [
        'auth' => 'AuthMiddleware',
        'guest' => 'GuestMiddleware',
        'admin' => 'AdminMiddleware',
        'doctor' => 'DoctorMiddleware',
        'patient' => 'PatientMiddleware',
        'verified' => 'VerifiedMiddleware',
        'throttle' => 'ThrottleMiddleware',
        'cors' => 'CorsMiddleware',
        'csrf' => 'CsrfMiddleware'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->parseUri();
    }

    /**
     * Parse the URI and remove query string
     *
     * @return string
     */
    private function parseUri()
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        $path = parse_url($path, PHP_URL_PATH);

        // Remove base path if in subdirectory
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }

        // Remove trailing slash except for root
        $path = rtrim($path, '/') ?: '/';

        return $path;
    }

    /**
     * Register a GET route
     *
     * @param string $path Route path
     * @param string|callable $controller Controller action
     * @param array $middleware Route middleware
     *
     * @return void
     */
    public function get($path, $controller, $middleware = [])
    {
        $this->registerRoute('GET', $path, $controller, $middleware);
    }

    /**
     * Register a POST route
     *
     * @param string $path Route path
     * @param string|callable $controller Controller action
     * @param array $middleware Route middleware
     *
     * @return void
     */
    public function post($path, $controller, $middleware = [])
    {
        $this->registerRoute('POST', $path, $controller, $middleware);
    }

    /**
     * Register a PUT route
     *
     * @param string $path Route path
     * @param string|callable $controller Controller action
     * @param array $middleware Route middleware
     *
     * @return void
     */
    public function put($path, $controller, $middleware = [])
    {
        $this->registerRoute('PUT', $path, $controller, $middleware);
    }

    /**
     * Register a DELETE route
     *
     * @param string $path Route path
     * @param string|callable $controller Controller action
     * @param array $middleware Route middleware
     *
     * @return void
     */
    public function delete($path, $controller, $middleware = [])
    {
        $this->registerRoute('DELETE', $path, $controller, $middleware);
    }

    /**
     * Register a PATCH route
     *
     * @param string $path Route path
     * @param string|callable $controller Controller action
     * @param array $middleware Route middleware
     *
     * @return void
     */
    public function patch($path, $controller, $middleware = [])
    {
        $this->registerRoute('PATCH', $path, $controller, $middleware);
    }

    /**
     * Register routes for multiple HTTP methods
     *
     * @param array $methods HTTP methods
     * @param string $path Route path
     * @param string|callable $controller Controller action
     * @param array $middleware Route middleware
     *
     * @return void
     */
    public function match($methods, $path, $controller, $middleware = [])
    {
        foreach ($methods as $method) {
            $this->registerRoute(strtoupper($method), $path, $controller, $middleware);
        }
    }

    /**
     * Register a route for all HTTP methods
     *
     * @param string $path Route path
     * @param string|callable $controller Controller action
     * @param array $middleware Route middleware
     *
     * @return void
     */
    public function any($path, $controller, $middleware = [])
    {
        $this->match(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $path, $controller, $middleware);
    }

    /**
     * Register a route
     *
     * @param string $method HTTP method
     * @param string $path Route path
     * @param string|callable $controller Controller action
     * @param array $middleware Route middleware
     *
     * @return void
     */
    private function registerRoute($method, $path, $controller, $middleware = [])
    {
        $path = '/' . ltrim($path, '/');

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->convertPathToRegex($path),
            'controller' => $controller,
            'middleware' => $middleware
        ];
    }

    /**
     * Convert route path to regex pattern
     * Supports {id}, {slug}, {uuid} parameters
     *
     * @param string $path Route path
     *
     * @return string
     */
    private function convertPathToRegex($path)
    {
        $pattern = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', function($matches) {
            $name = $matches[1];
            $regex = $matches[2] ?? '\d+'; // Default to digits

            // Common patterns
            $patterns = [
                'id' => '\d+',
                'slug' => '[a-z0-9-]+',
                'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
                'username' => '[a-zA-Z0-9_-]+',
                'email' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
            ];

            if (isset($patterns[$name])) {
                $regex = $patterns[$name];
            }

            return '(?P<' . $name . '>' . $regex . ')';
        }, $path);

        return '#^' . $pattern . '$#';
    }

    /**
     * Find matching route
     *
     * @return array|null
     */
    private function findRoute()
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->method && $route['method'] !== 'ANY') {
                continue;
            }

            if (preg_match($route['pattern'], $this->path, $matches)) {
                // Extract named parameters
                foreach ($matches as $key => $value) {
                    if (!is_numeric($key)) {
                        $this->params[$key] = $value;
                    }
                }

                return $route;
            }
        }

        return null;
    }

    /**
     * Dispatch the request to the appropriate controller
     *
     * @return mixed
     * @throws Exception
     */
    public function dispatch()
    {
        $route = $this->findRoute();

        if (!$route) {
            return $this->handleNotFound();
        }

        // Execute middleware
        $this->executeMiddleware($route['middleware']);

        // Execute controller
        return $this->executeController($route['controller']);
    }

    /**
     * Execute route middleware
     *
     * @param array $middleware Middleware to execute
     *
     * @return void
     */
    private function executeMiddleware($middleware)
    {
        foreach ($middleware as $m) {
            // Get middleware class name from named middleware or use directly
            $middlewareClass = $this->namedMiddleware[$m] ?? $m;

            if (!class_exists($middlewareClass)) {
                throw new Exception("Middleware class not found: $middlewareClass");
            }

            $instance = new $middlewareClass();

            if (!method_exists($instance, 'handle')) {
                throw new Exception("Middleware must have 'handle' method: $middlewareClass");
            }

            // If middleware returns false, stop execution
            if ($instance->handle() === false) {
                Response::unauthorized('Unauthorized access')->json();
            }
        }
    }

    /**
     * Execute controller action
     *
     * @param string|callable $controller Controller action
     *
     * @return mixed
     */
    private function executeController($controller)
    {
        if (is_callable($controller)) {
            return call_user_func_array($controller, [$this->params]);
        }

        // Parse controller string "ControllerName@methodName"
        if (is_string($controller)) {
            [$controllerClass, $method] = explode('@', $controller);

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller class not found: $controllerClass");
            }

            $instance = new $controllerClass();

            if (!method_exists($instance, $method)) {
                throw new Exception("Method not found: $controllerClass@$method");
            }

            return call_user_func_array([$instance, $method], [$this->params]);
        }

        throw new Exception("Invalid controller definition");
    }

    /**
     * Handle 404 not found
     *
     * @return void
     */
    private function handleNotFound()
    {
        Response::notFound('Page not found')->json();
    }

    /**
     * Get route parameters
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get current request method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get current request path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Add named middleware
     *
     * @param string $name Middleware name
     * @param string $class Middleware class
     *
     * @return void
     */
    public function addMiddleware($name, $class)
    {
        $this->namedMiddleware[$name] = $class;
    }

    /**
     * Add middleware group
     *
     * @param string $group Group name
     * @param array $middleware Middleware array
     *
     * @return void
     */
    public function addMiddlewareGroup($group, $middleware = [])
    {
        $this->middlewareGroups[$group] = $middleware;
    }
}

?>
