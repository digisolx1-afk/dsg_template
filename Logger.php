<?php
/**
 * Digital Sehat Ghar - Logger
 * ==========================
 * Handles application logging for debugging and error tracking
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

class Logger
{
    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    /**
     * Log file path
     *
     * @var string
     */
    private static $logPath = '';

    /**
     * Maximum log file size in MB
     *
     * @var int
     */
    private static $maxFileSize = 10;

    /**
     * Initialize logger
     *
     * @return void
     */
    public static function init()
    {
        self::$logPath = LOGS_PATH;

        // Create logs directory if not exists
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }

        // Set error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    /**
     * Log emergency level message
     *
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return void
     */
    public static function emergency($message, $context = [])
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log alert level message
     *
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return void
     */
    public static function alert($message, $context = [])
    {
        self::log(self::ALERT, $message, $context);
    }

    /**
     * Log critical level message
     *
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return void
     */
    public static function critical($message, $context = [])
    {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * Log error level message
     *
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return void
     */
    public static function error($message, $context = [])
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Log warning level message
     *
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return void
     */
    public static function warning($message, $context = [])
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Log notice level message
     *
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return void
     */
    public static function notice($message, $context = [])
    {
        self::log(self::NOTICE, $message, $context);
    }

    /**
     * Log info level message
     *
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return void
     */
    public static function info($message, $context = [])
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Log debug level message
     *
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return void
     */
    public static function debug($message, $context = [])
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Main logging function
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     *
     * @return void
     */
    public static function log($level, $message, $context = [])
    {
        // Skip if log level is not enabled
        if (!self::shouldLog($level)) {
            return;
        }

        $logEntry = self::formatLogEntry($level, $message, $context);
        self::writeLog($logEntry, $level);
    }

    /**
     * Determine if a log level should be logged
     *
     * @param string $level Log level
     *
     * @return bool
     */
    private static function shouldLog($level)
    {
        $levels = [
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7
        ];

        $currentLevel = $levels[LOG_LEVEL] ?? 6;
        $messageLevel = $levels[$level] ?? 6;

        return $messageLevel <= $currentLevel;
    }

    /**
     * Format log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     *
     * @return string Formatted log entry
     */
    private static function formatLogEntry($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);

        // Add context information
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | ' . json_encode($context);
        }

        // Add request information if available
        $requestInfo = '';
        if (!empty($_SERVER['REQUEST_METHOD'])) {
            $requestInfo = ' | ' . $_SERVER['REQUEST_METHOD'] . ' ' . ($_SERVER['REQUEST_URI'] ?? '');
        }

        // Add user information if available
        $userInfo = '';
        if (isset($_SESSION['user_id'])) {
            $userInfo = ' | User: ' . $_SESSION['user_id'];
        }

        $entry = "[$timestamp] [$level] $message$requestInfo$userInfo$contextStr\n";

        return $entry;
    }

    /**
     * Write log entry to file
     *
     * @param string $entry Log entry
     * @param string $level Log level
     *
     * @return void
     */
    private static function writeLog($entry, $level)
    {
        try {
            // Separate logs by level
            $logFile = self::$logPath . strtolower($level) . '.log';

            // Check if file size exceeds limit and rotate if needed
            self::rotateLogIfNeeded($logFile);

            // Write to file
            file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

            // Also write to general log file
            $generalLogFile = self::$logPath . 'app.log';
            file_put_contents($generalLogFile, $entry, FILE_APPEND | LOCK_EX);

        } catch (Exception $e) {
            // Fallback to error_log if file writing fails
            error_log($entry);
        }
    }

    /**
     * Rotate log file if it exceeds max size
     *
     * @param string $logFile Log file path
     *
     * @return void
     */
    private static function rotateLogIfNeeded($logFile)
    {
        if (!file_exists($logFile)) {
            return;
        }

        $maxSize = self::$maxFileSize * 1024 * 1024; // Convert MB to bytes
        $fileSize = filesize($logFile);

        if ($fileSize > $maxSize) {
            // Rotate old logs
            for ($i = LOG_MAX_FILES - 1; $i >= 1; $i--) {
                $oldFile = $logFile . '.' . $i;
                $newFile = $logFile . '.' . ($i + 1);

                if (file_exists($oldFile)) {
                    if (file_exists($newFile)) {
                        unlink($newFile);
                    }
                    rename($oldFile, $newFile);
                }
            }

            // Rename current log to .1
            rename($logFile, $logFile . '.1');
        }
    }

    /**
     * Handle PHP errors as exceptions
     *
     * @param int $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file
     * @param int $errline Error line
     *
     * @return void
     */
    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        $errorLevel = error_reporting();
        if (!($errno & $errorLevel)) {
            return;
        }

        $context = [
            'file' => $errfile,
            'line' => $errline,
            'error_no' => $errno
        ];

        self::error("PHP Error: $errstr", $context);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     *
     * @param Throwable $exception Exception object
     *
     * @return void
     */
    public static function handleException($exception)
    {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        self::critical('Uncaught Exception: ' . $exception->getMessage(), $context);
    }

    /**
     * Log database queries (for debugging)
     *
     * @param string $query SQL query
     * @param float $executionTime Execution time in ms
     *
     * @return void
     */
    public static function query($query, $executionTime = 0)
    {
        if (DEBUG_MODE) {
            $context = [
                'execution_time_ms' => $executionTime
            ];
            self::debug("Query: $query", $context);
        }
    }

    /**
     * Log user actions (for audit trail)
     *
     * @param string $action Action performed
     * @param array $details Action details
     *
     * @return void
     */
    public static function audit($action, $details = [])
    {
        $context = array_merge([
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ], $details);

        self::info("User Action: $action", $context);
    }

    /**
     * Clear old log files
     *
     * @param int $daysOld Delete logs older than this many days
     *
     * @return void
     */
    public static function clearOldLogs($daysOld = 30)
    {
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);

        $files = glob(self::$logPath . '*.log*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }

        self::info("Old log files cleared (older than $daysOld days)");
    }

    /**
     * Get recent log entries
     *
     * @param int $lines Number of lines to retrieve
     * @param string $level Log level filter (optional)
     *
     * @return array
     */
    public static function getRecent($lines = 100, $level = null)
    {
        $logFile = $level ? self::$logPath . strtolower($level) . '.log' : self::$logPath . 'app.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $file = fopen($logFile, 'r');
        $logs = [];

        while (!feof($file)) {
            $line = fgets($file);
            if (!empty($line)) {
                array_unshift($logs, $line);
            }
        }

        fclose($file);

        return array_slice($logs, 0, $lines);
    }

    /**
     * Display logs in web interface (for debugging)
     *
     * @return void
     */
    public static function displayLogs()
    {
        if (!DEBUG_MODE) {
            return;
        }

        $logs = self::getRecent(50);

        echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">';
        echo '<strong>Recent Application Logs:</strong><br><br>';

        foreach ($logs as $log) {
            echo htmlspecialchars($log);
        }

        echo '</pre>';
    }
}

// Initialize logger on first use
Logger::init();

?>
