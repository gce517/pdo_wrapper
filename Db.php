<?php /** @noinspection PhpUndefinedConstantInspection */

/**
 * Inspired by https://phpdelusions.net/pdo/pdo_wrapper
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
     * @param      $query     (required) the sql query
     * @param null $arguments (optional) array of parameters to bind
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
     * @param string $table
     * @param array  $data
     *
     * @return int last insert id
     */
    public function insert ($table, $data) {
        $q = $this->buildQuery($table, $data);
        $this->run($q['sql'], $q['args']);

        return $this->lastInsertId();
    }

    /**
     * Updates a record in a given table
     *
     * @param string $table
     * @param array  $data
     * @param array  $where_and
     * @param array  $where_or
     *
     * @return int
     */
    public function update ($table, $data, $where_and, $where_or) {
        $q      = $this->buildQuery($table, $data, 'update', $where_and, $where_or);
        $update = $this->run($q['sql'], $q['args']);

        return $update->rowCount();
    }

    /**
     * Builds a query to insert or update data.
     *
     * @param string $table     (required) the name of the table
     * @param array  $data      (required) array of columns requested
     * @param string $action    (required) 'insert' (default) or 'update'
     * @param array  $where_and (optional) column = value as a string (['{field} {operand}' => ':parameter']). required
     *                          when using $where_or
     * @param array  $where_or  (optional) column = value as a string
     *
     * @return array of sql and arguments
     */
    private function buildQuery ($table, $data, $action = 'insert', $where_and = null, $where_or = null) {
        $fields = [];
        $args   = [];
        $sql    = ($action == 'insert' ? 'INSERT INTO' : 'UPDATE');
        $where  = '';

        if ($where_and != null) :
            $counter = 0;
            foreach ($where_and as $key => $value) :
                // $key includes key and operand; let's separate them
                $split   = explode(' ', $key);
                $key     = $split[0];
                $operand = $split[1];
                $where  .= ($counter == 0 ? 'WHERE' : 'AND') . ' `' . $key . '` ' . $operand . ' :' . $key . '_a' .
                    PHP_EOL;
                $args[':' . $key . '_a'] = $value;
                $counter++;
            endforeach;
            if ($where_or != null) :
                $counter = 0;
                foreach ($where_or as $key => $value) :
                    $split   = explode(' ', $key);
                    $key     = $split[0];
                    $operand = $split[1];
                    $where   .= 'OR `' . $key . '` ' . $operand . ' :' . $key . '_o' . PHP_EOL;
                    $args[':' . $key . '_o'] = $value;
                    $counter++;
                endforeach;
            endif;
        endif;

        foreach ($data as $key => $value) :
            $key                 = explode(' ', $key);
            $fields[]            = '`' . $key[0] . '` = :' . $key[0] . PHP_EOL;
            $args[':' . $key[0]] = $value;
        endforeach;

        $table = '`' . str_replace('`', '``', $table) . '`';

        $sql .= $table . PHP_EOL . 'SET ' . implode(',', $fields) . $where;

        $constructedQuery = [
            'sql'  => $sql,
            'args' => $args,
        ];

        return $constructedQuery;
    }
}
