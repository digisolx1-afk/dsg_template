<?php
/**
 * Digital Sehat Ghar - Database Connection Manager
 * ================================================
 * Handles database connection, query execution, and prepared statements
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

class Database
{
    /**
     * PDO instance
     *
     * @var PDO
     */
    private static $connection = null;

    /**
     * Query execution count for debugging
     *
     * @var int
     */
    private static $queryCount = 0;

    /**
     * Store last executed query for debugging
     *
     * @var string
     */
    private static $lastQuery = '';

    /**
     * Store query execution time
     *
     * @var float
     */
    private static $executionTime = 0;

    /**
     * Get database connection (Singleton)
     *
     * @return PDO Database connection
     * @throws Exception If connection fails
     */
    public static function connect()
    {
        if (self::$connection === null) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

                self::$connection = new PDO(
                    $dsn,
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_STRINGIFY_FETCHES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
                    ]
                );

                // Log successful connection
                if (DEBUG_MODE) {
                    Logger::info('Database connection established successfully');
                }

            } catch (PDOException $e) {
                Logger::error('Database connection failed: ' . $e->getMessage());
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }

    /**
     * Execute a SELECT query
     *
     * @param string $query SQL query with placeholders (:param)
     * @param array $params Query parameters
     * @param string $fetchMode Fetch mode (all, row, column)
     *
     * @return mixed Query results
     * @throws Exception If query execution fails
     */
    public static function select($query, $params = [], $fetchMode = 'all')
    {
        try {
            $stmt = self::executeQuery($query, $params);

            switch (strtolower($fetchMode)) {
                case 'row':
                    return $stmt->fetch();
                case 'column':
                    return $stmt->fetchColumn();
                case 'all':
                default:
                    return $stmt->fetchAll();
            }

        } catch (Exception $e) {
            Logger::error('SELECT Query Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute an INSERT query
     *
     * @param string $table Table name
     * @param array $data Column => value pairs
     *
     * @return int|bool Last inserted ID or false
     * @throws Exception If query execution fails
     */
    public static function insert($table, $data = [])
    {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));

            $query = "INSERT INTO " . DB_PREFIX . "$table ($columns) VALUES ($placeholders)";

            $stmt = self::executeQuery($query, array_values($data));

            return self::$connection->lastInsertId();

        } catch (Exception $e) {
            Logger::error('INSERT Query Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute an UPDATE query
     *
     * @param string $table Table name
     * @param array $data Column => value pairs to update
     * @param array $where Where conditions (column => value)
     *
     * @return int Number of affected rows
     * @throws Exception If query execution fails
     */
    public static function update($table, $data = [], $where = [])
    {
        try {
            if (empty($where)) {
                throw new Exception('WHERE clause is required for UPDATE');
            }

            $setClause = implode(', ', array_map(function ($col) {
                return "$col = ?";
            }, array_keys($data)));

            $whereClause = implode(' AND ', array_map(function ($col) {
                return "$col = ?";
            }, array_keys($where)));

            $query = "UPDATE " . DB_PREFIX . "$table SET $setClause WHERE $whereClause";

            $params = array_merge(array_values($data), array_values($where));
            $stmt = self::executeQuery($query, $params);

            return $stmt->rowCount();

        } catch (Exception $e) {
            Logger::error('UPDATE Query Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute a DELETE query
     *
     * @param string $table Table name
     * @param array $where Where conditions (column => value)
     *
     * @return int Number of affected rows
     * @throws Exception If query execution fails
     */
    public static function delete($table, $where = [])
    {
        try {
            if (empty($where)) {
                throw new Exception('WHERE clause is required for DELETE');
            }

            $whereClause = implode(' AND ', array_map(function ($col) {
                return "$col = ?";
            }, array_keys($where)));

            $query = "DELETE FROM " . DB_PREFIX . "$table WHERE $whereClause";

            $stmt = self::executeQuery($query, array_values($where));

            return $stmt->rowCount();

        } catch (Exception $e) {
            Logger::error('DELETE Query Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute a raw SQL query
     *
     * @param string $query Raw SQL query
     * @param array $params Query parameters
     *
     * @return PDOStatement Statement object
     * @throws Exception If query execution fails
     */
    public static function query($query, $params = [])
    {
        try {
            return self::executeQuery($query, $params);
        } catch (Exception $e) {
            Logger::error('Query Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute a prepared statement
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     *
     * @return PDOStatement
     * @throws Exception If execution fails
     */
    private static function executeQuery($query, $params = [])
    {
        $connection = self::connect();
        $startTime = microtime(true);

        try {
            $stmt = $connection->prepare($query);
            $stmt->execute($params);

            self::$executionTime = (microtime(true) - $startTime) * 1000; // in milliseconds
            self::$queryCount++;
            self::$lastQuery = $query;

            // Log slow queries in development
            if (DEBUG_MODE && self::$executionTime > 1000) {
                Logger::warning("Slow query detected ({self::$executionTime}ms): $query");
            }

            return $stmt;

        } catch (PDOException $e) {
            Logger::error("PDO Error: " . $e->getMessage() . " | Query: " . $query);
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Call a stored procedure
     *
     * @param string $procedureName Procedure name
     * @param array $params Parameters for procedure
     *
     * @return array Procedure results
     * @throws Exception If procedure execution fails
     */
    public static function callProcedure($procedureName, $params = [])
    {
        try {
            $placeholders = implode(',', array_fill(0, count($params), '?'));
            $query = "CALL " . DB_PREFIX . "$procedureName($placeholders)";

            $stmt = self::executeQuery($query, $params);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            Logger::error('Procedure Call Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Count rows in a table
     *
     * @param string $table Table name
     * @param array $where Where conditions (optional)
     *
     * @return int Row count
     */
    public static function count($table, $where = [])
    {
        try {
            $query = "SELECT COUNT(*) as count FROM " . DB_PREFIX . $table;

            if (!empty($where)) {
                $whereClause = implode(' AND ', array_map(function ($col) {
                    return "$col = ?";
                }, array_keys($where)));
                $query .= " WHERE $whereClause";
            }

            $result = self::select($query, array_values($where), 'row');
            return (int)$result['count'];

        } catch (Exception $e) {
            Logger::error('COUNT Query Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a record exists
     *
     * @param string $table Table name
     * @param array $where Where conditions
     *
     * @return bool True if record exists
     */
    public static function exists($table, $where = [])
    {
        return self::count($table, $where) > 0;
    }

    /**
     * Get the last inserted ID
     *
     * @return string Last inserted ID
     */
    public static function lastInsertId()
    {
        return self::connect()->lastInsertId();
    }

    /**
     * Start a transaction
     *
     * @return bool
     */
    public static function beginTransaction()
    {
        return self::connect()->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public static function commit()
    {
        return self::connect()->commit();
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public static function rollback()
    {
        return self::connect()->rollBack();
    }

    /**
     * Check if in transaction
     *
     * @return bool
     */
    public static function inTransaction()
    {
        return self::connect()->inTransaction();
    }

    /**
     * Get last query (for debugging)
     *
     * @return string
     */
    public static function getLastQuery()
    {
        return self::$lastQuery;
    }

    /**
     * Get query execution time
     *
     * @return float Execution time in milliseconds
     */
    public static function getExecutionTime()
    {
        return self::$executionTime;
    }

    /**
     * Get query count
     *
     * @return int
     */
    public static function getQueryCount()
    {
        return self::$queryCount;
    }

    /**
     * Close database connection
     *
     * @return void
     */
    public static function close()
    {
        self::$connection = null;
    }

    /**
     * Escape string for SQL
     *
     * @param string $string String to escape
     *
     * @return string Escaped string
     */
    public static function escape($string)
    {
        if (is_array($string)) {
            return array_map([self::class, 'escape'], $string);
        }

        return addslashes($string);
    }

    /**
     * Get database info for debugging
     *
     * @return array Database information
     */
    public static function getInfo()
    {
        return [
            'host' => DB_HOST,
            'port' => DB_PORT,
            'database' => DB_NAME,
            'charset' => DB_CHARSET,
            'queries_executed' => self::$queryCount,
            'last_query' => self::$lastQuery,
            'last_execution_time' => self::$executionTime . 'ms'
        ];
    }
}

?>
