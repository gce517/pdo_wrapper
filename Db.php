<?php
declare(strict_types=1);

namespace Core; // Change to your desired namespace

use PDO;

// This is not the correct place to put these.
// You should have a separate config class/file.
\define('DB_DRIVER', 'mysql');
\define('DB_HOST', 'mysql');
\define('DB_NAME', 'mysql');
\define('DB_CHAR', 'utf8');
\define('DB_USER', '');
\define('DB_PASS', '');

/**
 * Class Db
 *
 * Adapted from https://phpdelusions.net/pdo/pdo_wrapper and https://phpdelusions.net/pdo/sql_injection_example
 *
 * @package Core
 */
class Db {
    /**
     * @var \Core\Db
     */
    protected static $instance;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * Db constructor.
     */
    public function __construct()
    {
        $default_options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $dsn = DB_DRIVER . ':'
               . 'host=' . DB_HOST . ';'
               . 'dbname=' . DB_NAME . ';'
               . 'charset=' . DB_CHAR . ';';

        $this->pdo = new \PDO($dsn, DB_USER, DB_PASS, $default_options);
    }

    /**
     * Make the method universally available
     *
     * @return \Core\Db
     */
    public static function getDB(): Db
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Proxy to native PDO methods
     *
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        return \call_user_func_array([$this->pdo, $method], $args);
    }

    /**
     * Prepares and executes the query statement
     *
     * @param string $query     The sql query
     * @param array  $arguments Array of parameters to bind
     *
     * @return bool|\PDOStatement
     */
    public function run(string $query, array $arguments = [])
    {
        if ($arguments === null) :
            return $this->pdo->query($query);
        endif;

        $statement = $this->pdo->prepare($query);
        $statement->execute($arguments);

        return $statement;
    }

    /**
     * Inserts a new record in the given table
     *
     * @param string $table   The table into where the data will be inserted
     * @param array  $data    The data to insert [field => value]
     * @param array  $allowed Array of allowed fields
     *
     * @return int id of the inserted row
     */
    public function insert(string $table, array $data, array $allowed = []): int
    {
        $q     = $this->buildQuery($data, $this->getAllowedFields($data, $allowed));
        $query = 'INSERT INTO ' . $table . $q['sql'];
        $this->run($query, $q['args']);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Updates a record in a given table
     *
     * @param string $table   The table into where the data will be updated
     * @param array  $data    The data to update [field => value]
     * @param array  $where   [field => value] limited to '=' comparison operator
     * @param array  $allowed Array of allowed fields
     *
     * @return int Count of affected rows
     */
    public function update(string $table, array $data, array $where = [], array $allowed = []): int
    {
        $q      = $this->buildQuery($data, $this->getAllowedFields($data, $allowed), $where);
        $query  = 'UPDATE ' . $table . $q['sql'];
        $update = $this->run($query, $q['args']);

        return $update->rowCount();
    }

    /**
     * Builds a query to insert or update data.
     *
     * @param array $data    [field => value]
     * @param array $allowed Array of allowed fields
     * @param array $where   [field => value] limited to '=' comparison operator
     *
     * @return array The constructed query and arguments for binding
     */
    private function buildQuery(array $data, array $allowed = [], array $where = []): array
    {
        $arguments = [];
        $set_stmt  = '';
        $set_where = '';

        if (!empty($where)) {
            $counter = 0;
            foreach ($where as $key => $value) {
                $set_where .= ($counter ? 'AND' : 'WHERE');
                $set_where .= ' `' . $key . '` = :' . $key . PHP_EOL;

                $arguments[':' . $key] = $value;
                $counter++;
            }
        }

        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                $set_stmt .= '`' . str_replace('`', '``', $key) . '` = :' . $key . PHP_EOL . ',';

                $arguments[':' . $key] = $data[$key];
            }
        }

        $sql = PHP_EOL . 'SET ' . rtrim($set_stmt, ',') . $set_where;

        $constructed_query = [
            'sql'  => $sql,
            'args' => $arguments,
        ];

        return $constructed_query;
    }

    /**
     * Gets the array of allowed fields.
     *
     * @param array $data    The data to insert/update [field => value]
     * @param array $allowed Array of allowed fields, will default to data fields if empty
     *
     * @return array
     */
    private function getAllowedFields(array $data, array $allowed): array
    {
        if (empty($allowed)) {
            foreach ($data as $key => $value) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }
}
