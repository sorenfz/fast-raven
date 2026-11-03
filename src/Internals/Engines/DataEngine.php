<?php

namespace FastRaven\Internals\Engines;

use FastRaven\Exceptions\SecurityVulnerabilityException;

use FastRaven\Services\DataService;
use FastRaven\Services\LogService;
use FastRaven\Bee;

use FastRaven\Components\Data\ConditionList;
use FastRaven\Components\Data\Map;

use FastRaven\Types\QueryType;

/**
 * @internal
 */
final class DataEngine {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private static bool $ready = false;
    private array $pdo = [];
    private string $db = "";
        public function use(string $db): void { $this->db = $db; }
    private ?string $lastError = null;
        public function getLastError(): ?string { return $this->lastError; }

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * This function will create a new DataEngine if it is not already busy.
     * It will then call DataService::__getToWork() and pass the new DataEngine object.
     * The new DataEngine object will be returned.
     *
     * @return ?DataEngine The DataEngine object if it was successfully created, null otherwise.
     */
    public static function zap(): ?DataEngine {
        if(!self::$ready) {
            self::$ready = true;
            $inst = new DataEngine();
            DataService::__getToWork($inst);

            return $inst;
        }

        return null;
    }

    private function __construct() {

    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS

    /**
     * Reads a DB_* environment variable for the active connection, falling back to the default connection value.
     * Named connections use DB_<NAME>_<KEY> (e.g., DB_LOGS_NAME) and inherit any missing key from DB_<KEY>.
     */
    private function env(string $key, string $default = ""): string {
        $base = Bee::env("DB_$key", $default);
        return $this->db === "" ? $base : Bee::env("DB_" . strtoupper($this->db) . "_$key", $base);
    }

    /**
     * Returns the PDO object of the active connection, creating it on first use.
     * If the connection cannot be created, the error is logged and null is returned.
     */
    private function pdo(): ?\PDO {
        if(!isset($this->pdo[$this->db])) {
            try {
                $options = [
                    \PDO::ATTR_PERSISTENT => $this->env("PERSISTENT", "false") === "true",
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ];
                if($this->env("SSL", "false") === "true") {
                    $caPath = realpath($this->env("SSL_CA"));
                    if($caPath !== false) {
                        $options[\PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                        $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                    }
                }

                $dsn = "mysql:host={$this->env("HOST", "localhost")};port={$this->env("PORT", "3306")};dbname={$this->env("NAME")};charset=utf8mb4";
                $this->pdo[$this->db] = new \PDO($dsn, $this->env("USER", "root"), $this->env("PASS"), $options);
            } catch (\PDOException $e) {
                $this->lastError = $e->getMessage();
                LogService::error("PDOException: ".$e->getMessage());
                return null;
            }
        }

        return $this->pdo[$this->db];
    }

    /**
     * Prepares and executes a query on the active connection.
     * Errors are logged and return null, except inside transaction() where they propagate to trigger the rollback.
     */
    private function execute(string $query, array $vars): ?\PDOStatement {
        $pdo = $this->pdo();
        if($pdo === null) return null;

        try {
            $this->lastError = null;
            $stmt = $pdo->prepare($query);
            $stmt->execute($vars);
            return $stmt;
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            if($pdo->inTransaction()) throw $e;
            LogService::error("PDOException: ".$e->getMessage() . " -> $query");
            return null;
        }
    }

    /**
     * This function will sanitize the given parameters.
     * It takes a table name, an array of column names, and an optional order by clause as parameters.
     * It will then sanitize the given parameters and throw a SecurityVulnerabilityException if any are found.
     *
     * @param string $table The name of the table to sanitize.
     * @param string[] $cols The array of column names to sanitize.
     * @param string[] $cond The array of condition column names to sanitize.
     * @param string[] $joined The array of joined table names to sanitize.
     * @param string[] $joinedLeftCols The array of joined table left column names to sanitize.
     * @param string[] $joinedRightCols The array of joined table right column names to sanitize.
     * @param string $orderBy The optional order by clause to sanitize.
     * 
     * @throws SecurityVulnerabilityException If any possible SQL injection is found.
     */
    private function sanitizeParameters(string &$table, array &$cols, array &$cond = [], array &$joined = [], array &$joinedLeftCols = [], array &$joinedRightCols = [], string &$orderBy = ""): void {
        $regex = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/';

        if (!preg_match($regex, $table)) {
            throw new SecurityVulnerabilityException("Invalid table name: $table");
        }

        foreach ($cols as $col) {
            if (!preg_match($regex, $col)) {
                throw new SecurityVulnerabilityException("Invalid column name: $col");
            }
        }

        foreach ($cond as $col) {
            if (!preg_match($regex, $col)) {
                throw new SecurityVulnerabilityException("Invalid condition column name: $col");
            }
        }

        foreach ($joined as $join) {
            if (!preg_match($regex, $join)) {
                throw new SecurityVulnerabilityException("Invalid joined table name: $join");
            }
        }

        foreach ($joinedLeftCols as $joinLeftCol) {
            if (!preg_match($regex, $joinLeftCol)) {
                throw new SecurityVulnerabilityException("Invalid joined left column name: $joinLeftCol");
            }
        }

        foreach ($joinedRightCols as $joinRightCol) {
            if (!preg_match($regex, $joinRightCol)) {
                throw new SecurityVulnerabilityException("Invalid joined right column name: $joinRightCol");
            }
        }

        if ($orderBy && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?(,\s*[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?)*$/i', $orderBy)) {
            throw new SecurityVulnerabilityException("Invalid order by: $orderBy");
        }

        $escape = fn($s) => implode('.', array_map(fn($p) => "`" . str_replace("`", "``", $p) . "`", explode('.', $s)));

        $table = $escape($table);
        $cols = array_map($escape, $cols);
        $cond = array_map($escape, $cond);
        $joined = array_map($escape, $joined);
        $joinedLeftCols = array_map($escape, $joinedLeftCols);
        $joinedRightCols = array_map($escape, $joinedRightCols);
    }

    /**
     * This function will build a SQL query string based on the given parameters.
     * It takes a QueryType enum, a table name, an array of column names, and an optional array of condition key-value pairs.
     * It will then construct a query string based on the given parameters and return it.
     *
     * @param QueryType $type The type of query to construct.
     * @param string $table The name of the table to query.
     * @param string[] $cols The array of column names to query.
     * @param ConditionList $cond The optional array of condition key-value pairs.
     * @param string $orderBy The optional order by clause.
     * @param int $limit The optional limit clause.
     * @param int $offset The optional offset clause.
     * @param string[] $joined The optional array of joined table names.
     * @param string[] $joinedLeftCols The optional array of joined table left column names.
     * @param string[] $joinedRightCols The optional array of joined table right column names.
     * 
     * @throws SecurityVulnerabilityException If the query is unsafe.
     * 
     * @return string The constructed query string.
     */
    private function buildQuery(QueryType $type, string $table, array $cols, ?ConditionList $cond = null, string $orderBy = "", int $limit = 0, int $offset = 0, array $joined = [], array $joinedLeftCols = [], array $joinedRightCols = []): string {
        $q = "";
        
        try {
            $condCols = $cond !== null ? $cond->getAllLeftValues() : [];
            $this->sanitizeParameters($table, $cols, $condCols, $joined, $joinedLeftCols, $joinedRightCols, $orderBy);
            if($cond !== null) $cond->replaceAllLeftValues($condCols);
        } catch (SecurityVulnerabilityException $e) {
            throw new SecurityVulnerabilityException("Possible SQL injection detected. Query not executed -> ".$e->getMessage());
        }

        if($type == QueryType::SELECT) {
            $q = "SELECT " . implode( ",", $cols) . " FROM " . $table;
            if(!empty($joined)) {
                for($i = 0; $i < count($joined); $i++) {
                    $q .= " JOIN " . $joined[$i] . " ON " . $joinedLeftCols[$i]." = ". $joinedRightCols[$i];
                }
            }
            if($cond !== null) $q .= " WHERE " . implode(" AND ", array_map(fn($c) => $c->parseToQuery(), $cond->getRawData()));
            if($orderBy) $q .= " ORDER BY $orderBy";
            if($limit > 0) $q .= " LIMIT $limit";
            if($offset > 0) $q .= " OFFSET $offset";
        
        } else if($type == QueryType::COUNT) {
            $q = "SELECT COUNT(*) as count FROM " . $table;
            if($cond !== null) $q .= " WHERE " . implode(" AND ", array_map(fn($c) => $c->parseToQuery(), $cond->getRawData()));
        
        } else if($type == QueryType::INSERT) {
            $q = "INSERT INTO " . $table . "(" . implode(",", $cols) . ") VALUES (" . implode(",", array_fill(0, count($cols), "?")) . ")";
        
        } else if($type == QueryType::UPDATE) {
            $q = "UPDATE " . $table . " SET " . implode(",", array_map(fn($c) => "$c = ?", $cols));
            $q .= " WHERE " . implode(" AND ", array_map(fn($c) => $c->parseToQuery(), $cond->getRawData()));
        
        } else if($type == QueryType::DELETE) {
            $q = "DELETE FROM " . $table;
            $q .= " WHERE " . implode(" AND ", array_map(fn($c) => $c->parseToQuery(), $cond->getRawData()));
        }

        return "$q;";
    }

    /**
     * Executes a built query and shapes the result according to the query type.
     *
     * @return array|bool|int|null The result of the query, or null if an error occurred.
     */
    private function simpleRequestToDatabase(QueryType $type, string $query, array $vars = []): array|bool|int|null {
        $stmt = $this->execute($query, $vars);
        if($stmt === null) return null;

        return match($type) {
            QueryType::SELECT => $stmt->fetchAll(),
            QueryType::COUNT => (int)$stmt->fetchColumn(),
            QueryType::DELETE => $stmt->rowCount() > 0,
            default => true,
        };
    }

    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Executes a raw SQL query. Statements producing a result set return all rows, any other statement returns its affected row count.
     *
     * @param string $query The SQL query to execute.
     * @param array $vars The variables to bind to the query (positional or named).
     * 
     * @return array|int|null The rows or affected row count, or null if an error occurred.
     */
    public function raw(string $query, array $vars = []): array|int|null {
        $stmt = $this->execute($query, $vars);
        if($stmt === null) return null;

        return $stmt->columnCount() > 0 ? $stmt->fetchAll() : $stmt->rowCount();
    }

    /**
     * Executes a raw SQL query and returns the first row.
     *
     * @return array|null The first row, or null if there is none or an error occurred.
     */
    public function rawOne(string $query, array $vars = []): ?array {
        return $this->execute($query, $vars)?->fetch() ?: null;
    }

    /**
     * Executes a raw SQL query and returns the first column of the first row.
     *
     * @return mixed The scalar value, or null if there is none or an error occurred.
     */
    public function rawValue(string $query, array $vars = []): mixed {
        $value = $this->execute($query, $vars)?->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Runs the callback inside a transaction on the active connection.
     * The transaction is rolled back if the callback returns false, throws, or any query inside it fails.
     *
     * @return bool True if the transaction was committed, false otherwise.
     */
    public function transaction(callable $fn): bool {
        $pdo = $this->pdo();
        if($pdo === null) return false;

        try {
            $pdo->beginTransaction();
            if($fn() === false) throw new \RuntimeException("Transaction callback returned false.");
            return $pdo->commit();
        } catch (\Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $this->lastError = $e->getMessage();
            LogService::error("Transaction rolled back: ".$e->getMessage());
            return false;
        }
    }

    /**
     * Executes a SQL query to retrieve all rows from the database that match the given conditions.
     *
     * @param string $table The table to retrieve data from.
     * @param string[] $cols The columns to retrieve data from.
     * @param ConditionList $cond The conditions to filter the data with.
     * @param string $orderBy [optional] The ORDER BY clause (e.g., "name ASC", "created_at DESC").
     * @param int $limit [optional] The maximum number of rows to retrieve.
     * @param int $offset [optional] The number of rows to skip.
     * 
     * @return array|null The retrieved data, or null if an error occurred.
     */
    public function select(string $table, array $cols, ?ConditionList $cond = null, string $orderBy = "", int $limit = 0, int $offset = 0): ?array {
        try {
            $query = $this->buildQuery(QueryType::SELECT, $table, $cols, $cond, $orderBy, $limit, $offset);
            return $this->simpleRequestToDatabase(QueryType::SELECT, $query, $cond === null ? [] : $cond->getBindValues());
        } catch (SecurityVulnerabilityException $e) {
            LogService::error($e->getMessage());
            return null;
        }
    }

    /**
     * Executes a SQL query to retrieve all joined rows from the database that match the given conditions.
     *
     * @param string $table The table to retrieve data from.
     * @param string[] $joined The joined tables to retrieve data from.
     * @param string[] $joinedLeftCols The joined tables left columns to match.
     * @param string[] $joinedRightCols The joined tables right columns to match.
     * @param string[] $cols The columns to retrieve data from.
     * @param ConditionList $cond The conditions to filter the data with.
     * @param string $orderBy The optional order by clause to sanitize.
     * @param int $limit The optional limit to sanitize.
     * @param int $offset The optional offset to sanitize.
     * 
     * @return array|null The retrieved data, or null if an error occurred.
     */
    public function join(string $table, array $joined, array $joinedLeftCols, array $joinedRightCols, array $cols, ?ConditionList $cond = null, string $orderBy = "", int $limit = 0, int $offset = 0): ?array {
        try {
            $query = $this->buildQuery(QueryType::SELECT, $table, $cols, $cond, $orderBy, $limit, $offset, $joined, $joinedLeftCols, $joinedRightCols);
            return $this->simpleRequestToDatabase(QueryType::SELECT, $query, $cond === null ? [] : $cond->getBindValues());
        } catch (SecurityVulnerabilityException $e) {
            LogService::error($e->getMessage());
            return null;
        }
    }

    /**
     * Inserts a new row into the database.
     *
     * @param string $table The table to insert into.
     * @param string[] $cols The columns to insert data into.
     * @param array $values The values to insert into the columns.
     * 
     * @return bool True if the insertion was successful, false otherwise.
     */
    public function insert(string $table, array $cols, array $values): bool {
        try {
            $query = $this->buildQuery(QueryType::INSERT, $table, $cols);
            $res = $this->simpleRequestToDatabase(QueryType::INSERT, $query, $values);
            return $res === true;
        } catch (SecurityVulnerabilityException $e) {
            LogService::error($e->getMessage());
            return false;
        }
    }

    /**
     * Gets the ID of the last inserted row.
     *
     * @return int|null The last insert ID, or null if an error occurred.
     */
    public function getLastInsertId(): ?int {
        $id = $this->pdo()?->lastInsertId();
        return $id === null ? null : (int)$id;
    }

    /**
     * Updates rows in the database that match the given conditions.
     *
     * @param string $table The table to update rows in.
     * @param Map $cols The columns to update.
     * @param ConditionList $cond The conditions to filter the rows to update with.
     * 
     * @return bool True if the update was successful, false otherwise.
     */
    public function update(string $table, Map $cols, ConditionList $cond): bool {
        try {
            $query = $this->buildQuery(QueryType::UPDATE, $table, $cols->getAllKeys(), $cond);
            $res = $this->simpleRequestToDatabase(QueryType::UPDATE, $query, array_merge($cols->getAllValues(), $cond->getBindValues()));
            return $res === true;
        } catch (SecurityVulnerabilityException $e) {
            LogService::error($e->getMessage());
            return false;
        }
    }

    /**
     * Deletes rows from the database that match the given conditions.
     *
     * @param string $table The table to delete rows from.
     * @param ConditionList $cond The conditions to filter the rows to delete.
     * 
     * @return bool True if the deletion was successful, false otherwise.
     */
    public function delete(string $table, ConditionList $cond): bool {
        try {
            $query = $this->buildQuery(QueryType::DELETE, $table, [], $cond);
            $res = $this->simpleRequestToDatabase(QueryType::DELETE, $query, $cond->getBindValues());
            return $res === true;
        } catch (SecurityVulnerabilityException $e) {
            LogService::error($e->getMessage());
            return false;
        }
    }

    /**
     * Counts rows in the database that match the given conditions.
     *
     * @param string $table The table to count rows from.
     * @param ConditionList $cond The conditions to filter the rows to count.
     * 
     * @return int The number of rows that match the conditions.
     */
    public function count(string $table, ?ConditionList $cond = null): int {
        try {
            $query = $this->buildQuery(QueryType::COUNT, $table, [], $cond);
            $res = $this->simpleRequestToDatabase(QueryType::COUNT, $query, $cond === null ? [] : $cond->getBindValues());
            return $res ?? 0;
        } catch (SecurityVulnerabilityException $e) {
            LogService::error($e->getMessage());
            return 0;
        }
    }

    /**
     * Inserts multiple rows into the database in a single transaction.
     *
     * @param string $table The table to insert into.
     * @param string[] $cols The columns to insert data into.
     * @param array $valuesArray Array of value arrays to insert.
     * 
     * @return bool True if all insertions were successful, false otherwise.
     */
    public function insertBatch(string $table, array $cols, array $valuesArray): bool {
        if (empty($valuesArray)) return false;

        try {
            $query = $this->buildQuery(QueryType::INSERT, $table, $cols);
        } catch (SecurityVulnerabilityException $e) {
            LogService::error($e->getMessage());
            return false;
        }

        return $this->transaction(function() use ($query, $valuesArray) {
            foreach ($valuesArray as $values) $this->execute($query, $values);
        });
    }

    #/ METHODS
    #----------------------------------------------------------------------
}