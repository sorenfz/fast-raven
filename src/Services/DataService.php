<?php

namespace FastRaven\Services;

use FastRaven\Components\Data\ConditionList;
use FastRaven\Internals\Engines\DataEngine;

use FastRaven\Components\Data\Map;
use FastRaven\Components\Data\Condition;

final class DataService {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private static bool $ready = false;
    private static DataEngine $engine;

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    public static function __getToWork(DataEngine &$engine): void {
        if(!self::$ready) {
            self::$ready = true;
            self::$engine = $engine;
        }
    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS



    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Selects the active database connection for the rest of the request.
     * Named connections read DB_<NAME>_* environment variables and inherit missing keys from the default DB_* ones.
     *
     * @param string $db The connection name (e.g., "logs" for DB_LOGS_*). Empty string returns to the default connection.
     */
    public static function use(string $db = ""): void {
        if(self::$ready) {
            self::$engine->use($db);
        }
    }

    /**
     * Executes a raw SQL query.
     *
     * @param string $query The SQL query to execute.
     * @param array $vars [optional] The variables to bind to the query (positional "?" or named ":name").
     *
     * @warning NEVER TRUST USER INPUT. ONLY $vars ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return array|int|null The rows for result sets, the affected row count otherwise, or null if an error occurred.
     */
    public static function sql(string $query, array $vars = []): array|int|null {
        if(self::$ready) {
            return self::$engine->raw($query, $vars);
        }

        return null;
    }

    /**
     * Executes a raw SQL query and returns the first row.
     *
     * @warning NEVER TRUST USER INPUT. ONLY $vars ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return array|null The first row, or null if there is none or an error occurred.
     */
    #[\NoDiscard]
    public static function sqlOne(string $query, array $vars = []): ?array {
        if(self::$ready) {
            return self::$engine->rawOne($query, $vars);
        }

        return null;
    }

    /**
     * Executes a raw SQL query and returns the first column of the first row (e.g., COUNT(*), MAX(id)).
     *
     * @warning NEVER TRUST USER INPUT. ONLY $vars ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return mixed The scalar value, or null if there is none or an error occurred.
     */
    #[\NoDiscard]
    public static function sqlValue(string $query, array $vars = []): mixed {
        if(self::$ready) {
            return self::$engine->rawValue($query, $vars);
        }

        return null;
    }

    /**
     * Runs the callback inside a transaction on the active connection.
     * The transaction is rolled back if the callback returns false, throws, or any query inside it fails.
     *
     * @param callable $fn The queries to run. Must not nest another transaction.
     * 
     * @return bool True if the transaction was committed, false otherwise.
     */
    public static function transaction(callable $fn): bool {
        if(self::$ready) {
            return self::$engine->transaction($fn);
        }

        return false;
    }

    /**
     * Gets the error message of the last failed query, if any.
     *
     * @return string|null The PDO error message, or null if the last query succeeded.
     */
    public static function getLastError(): ?string {
        if(self::$ready) {
            return self::$engine->getLastError();
        }

        return null;
    }

    /**
     * Retrieves all rows from the database without any conditions.
     *
     * @param string $table The table to retrieve data from.
     * @param string[] $cols The columns to retrieve data from.
     * @param string $orderBy [optional] The ORDER BY clause (e.g., "name ASC", "created_at DESC").
     * @param int $limit [optional] The maximum number of rows to retrieve.
     * @param int $offset [optional] The number of rows to skip.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return array|null The retrieved data, or null if an error occurred.
     */
    public static function select(string $table, array $cols, string $orderBy = "", int $limit = 0, int $offset = 0): ?array {
        if(self::$ready) {
            return self::$engine->select($table, $cols, null, $orderBy, $limit, $offset);
        }

        return null;
    }

    /**
     * Retrieves all rows from the database that match the given conditions.
     *
     * @param string $table The table to retrieve data from.
     * @param string[] $cols The columns to retrieve data from.
     * @param ConditionList $conditions The conditions to filter the data with.
     * @param string $orderBy [optional] The ORDER BY clause (e.g., "name ASC", "created_at DESC").
     * @param int $limit [optional] The maximum number of rows to retrieve.
     * @param int $offset [optional] The number of rows to skip.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return array|null The retrieved data, or null if an error occurred.
     */
    public static function selectWhere(string $table, array $cols, ConditionList $conditions, string $orderBy = "", int $limit = 0, int $offset = 0): ?array {
        if(self::$ready) {
            return self::$engine->select($table, $cols, $conditions, $orderBy, $limit, $offset);
        }

        return null;
    }

    /**
     * Retrieves one row from the database that matches the given conditions.
     *
     * @param string $table The table to retrieve data from.
     * @param string[] $cols The columns to retrieve data from.
     * @param ConditionList $conditions The conditions to filter the data with.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return array|null The retrieved data, or null if an error occurred.
     */
    public static function selectOneWhere(string $table, array $cols, ConditionList $conditions): ?array {
        if(self::$ready) {
            return array_first(self::$engine->select($table, $cols, $conditions, "", 1) ?? []);
        }

        return null;
    }

    /**
     * Retrieves one row from the database that matches the given id.
     *
     * @param string $table The table to retrieve data from.
     * @param string[] $cols The columns to retrieve data from.
     * @param int $id The id of the row to retrieve.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return array|null The retrieved data, or null if an error occurred.
     */
    public static function selectOneById(string $table, array $cols, int $id): ?array {
        if(self::$ready) {
            return array_first(self::$engine->select($table, $cols, ConditionList::new([Condition::equals("id", $id)]), "", 1) ?? []);
        }

        return null;
    }

    /**
     * Retrieves all joinable rows from the database.
     * 
     * @warning All cols must be prefixed with the table name. (e.g., "table.col")
     * 
     * @param string $table The table to retrieve data from.
     * @param array $joinedTables Tables to join with.
     * @param Map $joinedTablesLinks Links to join the tables with.
     * @param array $cols The columns to retrieve data from.
     * @param string $orderBy [optional] The ORDER BY clause (e.g., "name ASC", "created_at DESC").
     * @param int $limit [optional] The maximum number of rows to retrieve.
     * @param int $offset [optional] The number of rows to skip.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return array|null The retrieved data, or null if an error occurred.
     */
    public static function join(string $table, array $joinedTables, Map $joinedTablesLinks, array $cols, string $orderBy = "", int $limit = 0, int $offset = 0): ?array {
        if(self::$ready) {
            return self::$engine->join($table, $joinedTables, $joinedTablesLinks->getAllKeys(), $joinedTablesLinks->getAllValues(), $cols, null, $orderBy, $limit, $offset);
        }

        return null;
    }

    /**
     * Retrieves all joinable rows from the database that match the given conditions.
     * 
     * @warning All cols must be prefixed with the table name. (e.g., "table.col")
     * 
     * @param string $table The table to retrieve data from.
     * @param array $joinedTables The tables to join with.
     * @param Map $joinedTablesLinks Links to join the tables with.
     * @param array $cols The columns to retrieve data from.
     * @param ConditionList $conditions The conditions to filter the data with.
     * @param string $orderBy [optional] The ORDER BY clause (e.g., "name ASC", "created_at DESC").
     * @param int $limit [optional] The maximum number of rows to retrieve.
     * @param int $offset [optional] The number of rows to skip.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return array|null The retrieved data, or null if an error occurred.
     */
    public static function joinWhere(string $table, array $joinedTables, Map $joinedTablesLinks, array $cols, ConditionList $conditions, string $orderBy = "", int $limit = 0, int $offset = 0): ?array {
        if(self::$ready) {
            return self::$engine->join($table, $joinedTables, $joinedTablesLinks->getAllKeys(), $joinedTablesLinks->getAllValues(), $cols, $conditions, $orderBy, $limit, $offset);
        }

        return null;
    }

    /**
     * Inserts a new row into the database.
     *
     * @param string $table The table to insert into.
     * @param Map $columnValueMap Map of columns to insert data into and their values.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return bool True if the insertion was successful, false otherwise.
     */
    public static function insert(string $table, Map $columnValueMap) : bool {
        if(self::$ready) {
            return self::$engine->insert($table, $columnValueMap->getAllKeys(), $columnValueMap->getAllValues());
        }

        return false;
    }

    
    /**
     * Inserts multiple rows into the database in a single transaction.
     *
     * @param string $table The table to insert into.
     * @param Map[] $columnValueMapList List of Maps of columns to insert data into and their values.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return bool True if all insertions were successful, false otherwise.
     */
    public static function insertBatch(string $table, array $columnValueMapList): bool {
        if(self::$ready) {
            if(!empty($columnValueMapList)) {
                $cols = $columnValueMapList[0]->getAllKeys();
                $values = [];
                foreach($columnValueMapList as $columnValueMap) {
                    $values[] = $columnValueMap->getAllValues();
                }
                return self::$engine->insertBatch($table, $cols, $values);
            }
        }

        return false;
    }

    /**
     * Gets the ID of the last inserted row.
     *
     * @return int|null The last insert ID, or null if an error occurred.
     */
    #[\NoDiscard]
    public static function getLastInsertId(): ?int {
        if(self::$ready) {
            return self::$engine->getLastInsertId();
        }

        return null;
    }

    /**
     * Updates existing rows in the database that match the given conditions.
     *
     * @param string $table The table to update rows in.
     * @param Map $columnValueMap Map of columns to update and their new values.
     * @param ConditionList $conditions List of conditions to filter the rows to update with.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return bool True if the update was successful, false otherwise.
     */
    public static function updateWhere(string $table, Map $columnValueMap, ConditionList $conditions) : bool {
        if(self::$ready) {
            return self::$engine->update($table, $columnValueMap, $conditions);
        }

        return false;
    }

    /**
     * Updates a single row in the database by its ID.
     *
     * @param string $table The table to update the row in.
     * @param Map $columnValueMap Map of columns to update and their new values.
     * @param int $id The ID of the row to update.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return bool True if the update was successful, false otherwise.
     */
    public static function updateById(string $table, int $id, Map $columnValueMap): bool {
        if(self::$ready) {
            return self::$engine->update($table, $columnValueMap, ConditionList::new([Condition::equals("id", $id)]));
        }

        return false;
    }

    /**
     * Deletes a single row from the database by its ID.
     *
     * @param string $table The table to delete the row from.
     * @param int $id The ID of the row to delete.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return bool True if the deletion was successful, false otherwise.
     */
    public static function deleteById(string $table, int $id): bool {
        if(self::$ready) {
            return self::$engine->delete($table, ConditionList::new([Condition::equals("id", $id)]));
        }

        return false;
    }

    /**
     * Deletes rows from the database that match the given conditions.
     *
     * @param string $table The table to delete rows from.
     * @param ConditionList $conditionList List of conditions to filter the rows to delete.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return bool True if the deletion was successful, false otherwise.
     */
    public static function deleteWhere(string $table, ConditionList $conditionList): bool {
        if(self::$ready) {
            return self::$engine->delete($table, $conditionList);
        }

        return false;   
    }

    /**
     * Counts the number of rows in the database that match the given conditions.
     *
     * @param string $table The table to count rows from.
     * @param ConditionList $conditionList List of conditions to filter the rows to count.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return int The number of rows that match the conditions.
     */
    public static function count(string $table, ConditionList $conditionList): int {
        if(self::$ready) {
            return self::$engine->count($table, $conditionList);
        }

        return 0;
    }

    /**
     * Counts all rows in the database table.
     *
     * @param string $table The table to count rows from.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return int The total number of rows in the table.
     */
    public static function countAll(string $table): int {
        if(self::$ready) {
            return self::$engine->count($table, null);
        }

        return 0;
    }

    /**
     * Checks if a row exists in the database that matches the given conditions.
     *
     * @param string $table The table to check for existence.
     * @param ConditionList $conditionList List of conditions to filter the rows.
     * 
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return bool True if at least one row exists, false otherwise.
     */
    public static function exists(string $table, ConditionList $conditionList): bool {
        if(self::$ready) {
            return self::$engine->count($table, $conditionList) > 0;
        }

        return false;
    }

    /**
     * Checks if a row with the given ID exists in the database.
     *
     * @param string $table The table to check for existence.
     * @param int $id The ID to check for.
     *
     * @warning NEVER TRUST USER INPUT. ONLY MAP VARIABLES ARE PROTECTED AGAINST SQL INJECTION.
     * 
     * @return bool True if a row with the given ID exists, false otherwise.
     */
    public static function existsById(string $table, int $id): bool {
        if(self::$ready) {
            return self::$engine->count($table, ConditionList::new([Condition::equals("id", $id)])) > 0;
        }

        return false;
    }

    #/ METHODS
    #----------------------------------------------------------------------
}