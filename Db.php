<?php /** @noinspection PhpUndefinedConstantInspection */

/**
 * Inspired by https://phpdelusions.net/pdo/pdo_wrapper and https://phpdelusions.net/pdo/sql_injection_example
 */

namespace Core;

use PDO;

/**
 * Class Db
 *
 * @package Core
 */
class Db extends PDO {
    // Expects constants to be pre-defined in your application or you can enter them here
    private $dsn    = 'mysql:';
    private $dbhost = 'host=' . DBHOST . ';';
    private $dbname = 'dbname=' . DBNAME . ';';
    private $dbuser = DBUSER;
    private $dbpass = DBPASS;
    private $chrset = 'charset=utf8';

    /**
     * Class constructor
     *
     * @param array $options
     */
    public function __construct ($options = []) {
        $default_options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $options = array_merge($default_options, $options);

        parent::__construct(
            $this->dsn . $this->dbhost . $this->dbname . $this->chrset,
            $this->dbuser,
            $this->dbpass,
            $options
        );
    }

    /**
     * Prepares and executes the query statement
     *
     * @param string $query     (required) the sql query
     * @param null   $arguments (optional) array of parameters to bind
     *
     * @return \PDOStatement
     */
    public function run ($query, $arguments = null) {
        if ($arguments == null) :
            return $this->query($query);
        endif;

        $statement = $this->prepare($query);
        $statement->execute($arguments);

        return $statement;
    }

    /**
     * Inserts a new record in the given table
     *
     * @param string $table   The table into where the data will be inserted
     * @param array  $data    The data to insert [column => value]
     * @param array  $allowed array of allowed fields
     *
     * @return int last insert id
     */
    public function insert ($table, $data, $allowed = []) {
        $allowed = $this->getAllowedFields($data, $allowed);
        $q      = $this->buildQuery($data, $allowed);
        $query  = 'INSERT INTO ' . $table . $q['sql'];
        $insert = $this->run($query, $q['args']);

        return $insert->lastInsertId();
    }

    /**
     * Updates a record in a given table
     *
     * @param string $table   The table into where the data will be updated
     * @param array  $data    The data to insert [column => value]
     * @param array  $where   [field => value]
     * @param array  $allowed array of allowed fields
     *
     * @return int number of rows affected
     */
    public function update ($table, $data, $where = [], $allowed) {
        $allowed = $this->getAllowedFields($data, $allowed);
        $q      = $this->buildQuery($data, $allowed, $where);
        $query  = 'UPDATE ' . $table . $q['sql'];
        $update = $this->run($query, $q['args']);

        return $update->rowCount();
    }

    /**
     * Builds a query to insert or update data.
     *
     * @param array  $data    (required) [column => value]
     * @param array  $allowed (optional) array of allowed fields
     * @param array  $where   (optional) [column => value]
     *
     * @return array of sql and arguments
     */
    private function buildQuery ($data, $allowed = [], $where = []) {
        $args      = [];
        $where_out = '';
        $setStr    = '';

        if (!empty($where)) :
            $counter = 0;
            foreach ($where as $key => $value) :
                $where_out .= ($counter ? 'AND' : 'WHERE');
                $where_out .= ' `' . $key . '` = :' . $key . PHP_EOL;
                $args[':' . $key] = $value;
                $counter++;
            endforeach;
        endif;

        foreach ($allowed as $key) :
            if (isset($data[$key])) :
                $setStr .= '`' . str_replace('`', '``', $key) . '` = :' . $key . PHP_EOL . ',';
                $args[':' . $key] = $data[$key];
            endif;
        endforeach;

        $sql = PHP_EOL . 'SET ' . rtrim($setStr, ',') . $where_out;

        $constructedQuery = [
            'sql'  => $sql,
            'args' => $args,
        ];

        return $constructedQuery;
    }

    /**
     * Generates an array with the allowed fields
     *
     * @param $data
     * @param $allowed
     *
     * @return array
     */
    private function getAllowedFields ($data, $allowed) {
        if (empty($allowed)) :
            foreach ($data as $key => $value) :
                $allowed[] = $key;
            endforeach;
        endif;

        return $allowed;
    }
}
