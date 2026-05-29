<?php
/**
 * Digital Sehat Ghar - PSR-4 Autoloader
 * ====================================
 * Automatically loads classes based on PSR-4 standard
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

class Autoloader
{
    /**
     * Namespaces mapping to directories
     *
     * @var array
     */
    private static $prefixes = [];

    /**
     * Register the autoloader
     *
     * @return void
     */
    public static function register()
    {
        spl_autoload_register([self::class, 'loadClass']);
    }

    /**
     * Add a namespace prefix mapping
     *
     * @param string $prefix The namespace prefix
     * @param string $baseDir The base directory path
     * @param bool $prepend Whether to prepend to stack (for priority)
     *
     * @return void
     */
    public static function addNamespace($prefix, $baseDir, $prepend = false)
    {
        // Normalize the namespace prefix
        $prefix = trim($prefix, '\\') . '\\';

        // Normalize the base directory
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';

        if (isset(self::$prefixes[$prefix]) === false) {
            self::$prefixes[$prefix] = [];
        }

        if ($prepend) {
            array_unshift(self::$prefixes[$prefix], $baseDir);
        } else {
            array_push(self::$prefixes[$prefix], $baseDir);
        }
    }

    /**
     * Load a class file based on namespace
     *
     * @param string $class The fully-qualified class name
     *
     * @return mixed The loaded file name on success, or false
     */
    public static function loadClass($class)
    {
        // The current namespace prefix
        $prefix = $class;

        // Work backwards through the namespace names of the fully-qualified
        // class name to find a mapped file name
        while (false !== $pos = strrpos($prefix, '\\')) {
            // Retain the trailing namespace separator in the prefix
            $prefix = substr($class, 0, $pos + 1);

            // The rest is the relative class name
            $relativeClass = substr($class, $pos + 1);

            // Try to load a mapped file for the prefix and relative class
            $mapped_file = self::loadMappedFile($prefix, $relativeClass);

            if ($mapped_file) {
                return $mapped_file;
            }

            // Remove the trailing namespace separator for the next iteration
            // of strrpos()
            $prefix = rtrim($prefix, '\\');
        }

        // Never found a mapped file
        return false;
    }

    /**
     * Load the mapped file for a namespace prefix and relative class
     *
     * @param string $prefix The namespace prefix
     * @param string $relativeClass The relative class name
     *
     * @return mixed Boolean false if no mapped file can be loaded; the name of the mapped
     * file that was loaded
     */
    protected static function loadMappedFile($prefix, $relativeClass)
    {
        // Are there any base directories for this namespace prefix?
        if (isset(self::$prefixes[$prefix]) === false) {
            return false;
        }

        // Look through base directories for this namespace prefix
        foreach (self::$prefixes[$prefix] as $baseDir) {
            // Replace the namespace prefix with the base directory,
            // replace namespace separators with directory separators
            // in the relative class name, and append with .php
            $file = $baseDir
                . str_replace('\\', '/', $relativeClass)
                . '.php';

            // If the mapped file was found, return it
            if (self::requireFile($file)) {
                return $file;
            }
        }

        // Never found a mapped file
        return false;
    }

    /**
     * Require a file if it exists
     *
     * @param string $file The file to require
     *
     * @return bool True if the file exists and is loaded, false otherwise
     */
    protected static function requireFile($file)
    {
        if (file_exists($file)) {
            require $file;
            return true;
        }

        return false;
    }

    /**
     * Get all registered namespaces
     *
     * @return array
     */
    public static function getPrefixes()
    {
        return self::$prefixes;
    }

    /**
     * Clear all registered namespaces
     *
     * @return void
     */
    public static function clearPrefixes()
    {
        self::$prefixes = [];
    }
}

// ============================================================
// Register PSR-4 Namespaces
// ============================================================

Autoloader::register();

// Register application namespaces
Autoloader::addNamespace('App', ROOT_PATH . 'app');
Autoloader::addNamespace('Controllers', ROOT_PATH . 'controllers');
Autoloader::addNamespace('Models', ROOT_PATH . 'models');
Autoloader::addNamespace('Helpers', ROOT_PATH . 'helpers');
Autoloader::addNamespace('Database', ROOT_PATH . 'database');

?>
