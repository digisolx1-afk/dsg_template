<?php
/**
 * Digital Sehat Ghar - Response Handler
 * ====================================
 * Handles standardized API and HTML responses
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

class Response
{
    /**
     * HTTP status code
     *
     * @var int
     */
    private $statusCode = 200;

    /**
     * Response headers
     *
     * @var array
     */
    private $headers = [];

    /**
     * Response data
     *
     * @var mixed
     */
    private $data = [];

    /**
     * Response message
     *
     * @var string
     */
    private $message = '';

    /**
     * Constructor
     *
     * @param int $statusCode HTTP status code
     * @param string $message Response message
     * @param mixed $data Response data
     */
    public function __construct($statusCode = 200, $message = '', $data = [])
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
        $this->data = $data;
        $this->setDefaultHeaders();
    }

    /**
     * Set default response headers
     *
     * @return void
     */
    private function setDefaultHeaders()
    {
        $this->headers['Content-Type'] = 'application/json';
        $this->headers['X-Content-Type-Options'] = 'nosniff';
        $this->headers['X-Frame-Options'] = 'SAMEORIGIN';

        // Add security headers from config
        if (defined('SECURITY_HEADERS') && is_array(SECURITY_HEADERS)) {
            $this->headers = array_merge($this->headers, SECURITY_HEADERS);
        }
    }

    /**
     * Success response
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $statusCode HTTP status code (default: 200)
     *
     * @return Response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200)
    {
        $response = new self($statusCode, $message, $data);
        return $response;
    }

    /**
     * Error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default: 400)
     * @param array $errors Detailed error information
     *
     * @return Response
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = [])
    {
        $response = new self($statusCode, $message);
        if (!empty($errors)) {
            $response->data = $errors;
        }
        return $response;
    }

    /**
     * Not found response (404)
     *
     * @param string $message Error message
     *
     * @return Response
     */
    public static function notFound($message = 'Resource not found')
    {
        return self::error($message, 404);
    }

    /**
     * Unauthorized response (401)
     *
     * @param string $message Error message
     *
     * @return Response
     */
    public static function unauthorized($message = 'Unauthorized access')
    {
        return self::error($message, 401);
    }

    /**
     * Forbidden response (403)
     *
     * @param string $message Error message
     *
     * @return Response
     */
    public static function forbidden($message = 'Access forbidden')
    {
        return self::error($message, 403);
    }

    /**
     * Validation error response (422)
     *
     * @param array $errors Validation errors
     * @param string $message Error message
     *
     * @return Response
     */
    public static function validationError($errors = [], $message = 'Validation failed')
    {
        return self::error($message, 422, $errors);
    }

    /**
     * Server error response (500)
     *
     * @param string $message Error message
     *
     * @return Response
     */
    public static function serverError($message = 'Internal server error')
    {
        return self::error($message, 500);
    }

    /**
     * Created response (201)
     *
     * @param mixed $data Created resource data
     * @param string $message Success message
     *
     * @return Response
     */
    public static function created($data = null, $message = 'Resource created successfully')
    {
        return self::success($data, $message, 201);
    }

    /**
     * Paginated response
     *
     * @param array $items Items in current page
     * @param int $total Total items
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param string $message Success message
     *
     * @return Response
     */
    public static function paginated($items = [], $total = 0, $page = 1, $perPage = 10, $message = 'Data retrieved successfully')
    {
        $lastPage = ceil($total / $perPage) ?: 1;

        $data = [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'has_next' => $page < $lastPage,
                'has_prev' => $page > 1
            ]
        ];

        return self::success($data, $message);
    }

    /**
     * Set HTTP status code
     *
     * @param int $code HTTP status code
     *
     * @return Response
     */
    public function setStatus($code)
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Add custom header
     *
     * @param string $name Header name
     * @param string $value Header value
     *
     * @return Response
     */
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add multiple headers
     *
     * @param array $headers Headers array
     *
     * @return Response
     */
    public function setHeaders($headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Get header
     *
     * @param string $name Header name
     *
     * @return string|null
     */
    public function getHeader($name)
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set response data
     *
     * @param mixed $data Response data
     *
     * @return Response
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get response data
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set response message
     *
     * @param string $message Response message
     *
     * @return Response
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get response message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->statusCode;
    }

    /**
     * Build JSON response array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'status' => $this->statusCode,
            'message' => $this->message,
            'success' => $this->statusCode >= 200 && $this->statusCode < 300,
            'data' => $this->data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Send JSON response
     *
     * @return void
     */
    public function json()
    {
        http_response_code($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect to URL
     *
     * @param string $url Target URL
     * @param int $code HTTP redirect code
     *
     * @return void
     */
    public static function redirect($url, $code = 302)
    {
        http_response_code($code);
        header("Location: $url");
        exit;
    }

    /**
     * Download file
     *
     * @param string $filePath File path
     * @param string $filename Download filename
     *
     * @return void
     */
    public static function download($filePath, $filename = null)
    {
        if (!file_exists($filePath)) {
            self::error('File not found', 404)->json();
        }

        $filename = $filename ?: basename($filePath);
        $filesize = filesize($filePath);
        $mime = mime_content_type($filePath);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        exit;
    }

    /**
     * View response (render HTML template)
     *
     * @param string $view View file path (without .php)
     * @param array $data Data to pass to view
     *
     * @return void
     */
    public static function view($view, $data = [])
    {
        $viewPath = VIEWS_PATH . $view . '.php';

        if (!file_exists($viewPath)) {
            self::error('View not found: ' . $view, 404)->json();
        }

        extract($data);
        include $viewPath;
        exit;
    }

    /**
     * Flash message to session
     *
     * @param string $type Message type (success, error, warning, info)
     * @param string $message Message content
     *
     * @return void
     */
    public static function flash($type, $message)
    {
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }

        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Get and clear flash message
     *
     * @param string $type Message type
     *
     * @return string|null
     */
    public static function getFlash($type)
    {
        if (!isset($_SESSION['flash'][$type])) {
            return null;
        }

        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);

        return $message;
    }

    /**
     * Convert response to JSON string
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

?>
