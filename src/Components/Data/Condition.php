<?php

namespace FastRaven\Components\Data;

use FastRaven\Types\OperatorType;

class Condition {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private string $left;
        public function getLeft(): string { return $this->left; }
        public function setLeft(string $left): void { $this->left = $left; }
    private OperatorType $operator;
        public function getOperator(): OperatorType { return $this->operator; }
        public function setOperator(OperatorType $operator): void { $this->operator = $operator; }
    private string|int|float|bool|array|null $right;
        public function getRight(): string|int|float|bool|array|null { return $this->right; }
        public function setRight(string|int|float|bool|array|null $right): void { $this->right = $right; }

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * Create a new Condition instance.
     *
     * @param string $left What to compare.
     * @param OperatorType $operator The operator of the condition.
     * @param string|int|float|bool|array|null $right What to compare against.
     *
     * @return Condition
     */
    public static function new(string $left, OperatorType $operator, string|int|float|bool|array|null $right): Condition {
        return new Condition($left, $operator, $right);
    }

    /**
     * Create a new Condition instance with the operator EQUAL.
     *
     * @param string $left What to compare.
     * @param string|int|float|bool $right What to compare against.
     *
     * @return Condition
     */
    public static function equals(string $left, string|int|float|bool $right): Condition {
        return new Condition($left, OperatorType::EQUAL, $right);
    }

    /**
     * Create a new Condition instance with the operator NOT_EQUAL.
     *
     * @param string $left What to compare.
     * @param string|int|float|bool $right What to compare against.
     *
     * @return Condition
     */
    public static function notEquals(string $left, string|int|float|bool $right): Condition {
        return new Condition($left, OperatorType::NOT_EQUAL, $right);
    }

    /**
     * Create a new Condition instance with the operator GREATER_THAN.
     *
     * @param string $left What to compare.
     * @param int|float $right What to compare against.
     *
     * @return Condition
     */
    public static function greaterThan(string $left, int|float $right): Condition {
        return new Condition($left, OperatorType::GREATER_THAN, $right);
    }

    /**
     * Create a new Condition instance with the operator LESS_THAN.
     *
     * @param string $left What to compare.
     * @param int|float $right What to compare against.
     *
     * @return Condition
     */
    public static function lessThan(string $left, int|float $right): Condition {
        return new Condition($left, OperatorType::LESS_THAN, $right);
    }

    /**
     * Create a new Condition instance with the operator GREATER_THAN_OR_EQUAL.
     *
     * @param string $left What to compare.
     * @param int|float $right What to compare against.
     *
     * @return Condition
     */
    public static function greaterThanOrEqual(string $left, int|float $right): Condition {
        return new Condition($left, OperatorType::GREATER_THAN_OR_EQUAL, $right);
    }

    /**
     * Create a new Condition instance with the operator LESS_THAN_OR_EQUAL.
     *
     * @param string $left What to compare.
     * @param int|float $right What to compare against.
     *
     * @return Condition
     */
    public static function lessThanOrEqual(string $left, int|float $right): Condition {
        return new Condition($left, OperatorType::LESS_THAN_OR_EQUAL, $right);
    }

    /**
     * Create a new Condition instance with the operator LIKE.
     *
     * @param string $left What to compare.
     * @param string $right What to compare against.
     *
     * @return Condition
     */
    public static function like(string $left, string $right): Condition {
        return new Condition($left, OperatorType::LIKE, $right);
    }

    /**
     * Create a new Condition instance with the operator NOT_LIKE.
     *
     * @param string $left What to compare.
     * @param string $right What to compare against.
     *
     * @return Condition
     */
    public static function notLike(string $left, string $right): Condition {
        return new Condition($left, OperatorType::NOT_LIKE, $right);
    }

    /**
     * Create a new Condition instance with the operator IN.
     *
     * @param string $target What to compare.
     * @param array $variable What to compare against.
     *
     * @return Condition
     */
    public static function in(string $left, array $right): Condition {
        return new Condition($left, OperatorType::IN, $right);
    }

    /**
     * Create a new Condition instance with the operator NOT_IN.
     *
     * @param string $left What to compare.
     * @param array $right What to compare against.
     *
     * @return Condition
     */
    public static function notIn(string $left, array $right): Condition {
        return new Condition($left, OperatorType::NOT_IN, $right);
    }

    /**
     * Create a new Condition instance with the operator BETWEEN.
     *
     * @param string $left What to compare.
     * @param string|int|float $low Lower bound (inclusive).
     * @param string|int|float $high Upper bound (inclusive).
     *
     * @return Condition
     */
    public static function between(string $left, string|int|float $low, string|int|float $high): Condition {
        return new Condition($left, OperatorType::BETWEEN, [$low, $high]);
    }

    /**
     * Create a new Condition instance with the operator NOT_BETWEEN.
     *
     * @param string $left What to compare.
     * @param string|int|float $low Lower bound (inclusive).
     * @param string|int|float $high Upper bound (inclusive).
     *
     * @return Condition
     */
    public static function notBetween(string $left, string|int|float $low, string|int|float $high): Condition {
        return new Condition($left, OperatorType::NOT_BETWEEN, [$low, $high]);
    }

    /**
     * Create a new Condition instance with the operator IS. Renders as IS TRUE, IS FALSE or IS NULL.
     *
     * @param string $left What to compare.
     * @param ?bool $right What to compare against (null for IS NULL).
     *
     * @return Condition
     */
    public static function is(string $left, ?bool $right): Condition {
        return new Condition($left, OperatorType::IS, $right);
    }

    /**
     * Create a new Condition instance with the operator IS_NOT. Renders as IS NOT TRUE, IS NOT FALSE or IS NOT NULL.
     *
     * @param string $left What to compare.
     * @param ?bool $right What to compare against (null for IS NOT NULL).
     *
     * @return Condition
     */
    public static function isNot(string $left, ?bool $right): Condition {
        return new Condition($left, OperatorType::IS_NOT, $right);
    }

    /**
     * Create a new Condition instance that renders as IS NULL.
     *
     * @param string $left What to compare.
     *
     * @return Condition
     */
    public static function isNull(string $left): Condition {
        return new Condition($left, OperatorType::IS, null);
    }

    /**
     * Create a new Condition instance that renders as IS NOT NULL.
     *
     * @param string $left What to compare.
     *
     * @return Condition
     */
    public static function isNotNull(string $left): Condition {
        return new Condition($left, OperatorType::IS_NOT, null);
    }

    private function  __construct(string $left, OperatorType $operator, string|int|float|bool|array|null $right) {
        $this->left = $left;
        $this->right = $right;
        $this->operator = $operator;
    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS

    private function isLiteral(): bool {
        return in_array($this->operator, [OperatorType::IS, OperatorType::IS_NOT], true);
    }

    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Returns the values to bind for this condition. IS / IS NOT render literals and bind nothing.
     *
     * @return array The flat list of bind values.
     */
    public function getBindValues(): array {
        if($this->isLiteral()) return [];
        $values = is_array($this->right) ? array_values($this->right) : [$this->right];
        return array_map(fn($v) => is_bool($v) ? (int)$v : $v, $values);
    }

    /**
     * Renders the condition as a SQL fragment.
     *
     * @param bool $offuscate Whether to render placeholders (?) instead of raw values.
     *
     * @return string The SQL fragment (e.g., "col = ?", "col IN (?, ?)", "col BETWEEN ? AND ?", "col IS NULL").
     */
    public function parseToQuery(bool $offuscate = true): string {
        $op = $this->operator->value;
        $render = fn($v) => $offuscate ? "?" : (is_bool($v) ? (int)$v : $v);

        if($this->isLiteral()) return "{$this->left} $op " . match($this->right) { null => "NULL", true => "TRUE", false => "FALSE" };
        if(in_array($this->operator, [OperatorType::BETWEEN, OperatorType::NOT_BETWEEN], true)) return "{$this->left} $op {$render($this->right[0])} AND {$render($this->right[1])}";
        if(is_array($this->right)) return "{$this->left} $op (" . implode(", ", array_map($render, $this->right)) . ")";
        return "{$this->left} $op {$render($this->right)}";
    }

    #/ METHODS
    #----------------------------------------------------------------------
}