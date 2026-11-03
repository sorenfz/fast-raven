<?php

namespace FastRaven\Components\Data;


class ConditionList {
    #----------------------------------------------------------------------
    #\ VARIABLES

    protected array $data = [];
        public function getRawData(): array { return $this->data; }
        
    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * Create a new ConditionList instance.
     *
     * @param Condition[] $data The list of values to store in the ConditionList.
     *
     * @return ConditionList
     */
    public static function new(array $data = []): ConditionList {
        return new ConditionList($data);
    }

    protected function  __construct(array $data = []) {
        $this->data = [];
        foreach($data as $value) {
            if($value instanceof Condition) $this->data[] = $value;
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
     * Merges the given ConditionList into this instance.
     *
     * @param ConditionList $list The ConditionList to merge into this instance.
     *
     * @return ConditionList The updated ConditionList.
     */
    public function merge(ConditionList $list): ConditionList {
        $this->data = array_merge($this->data, $list->getRawData());
        return $this;
    }

    /**
     * Retrieves a value from the ConditionList by its index.
     *
     * @param int $index The index of the value to retrieve.
     *
     * @return mixed|null The value with the given index, or null if not found.
     */
    public function get(int $index): mixed {
        return $this->data[$index] ?? null;
    }

    /**
     * Returns an array of all left values in the ConditionList.
     *
     * @return array The list of left values in the ConditionList.
     */
    public function getAllLeftValues(): array {
        return array_map(fn($condition) => $condition->getLeft(), $this->data);
    }

    /**
     * Returns an array of all right values in the ConditionList.
     *
     * @return array The list of right values in the ConditionList.
     */
    public function getAllRightValues(): array {
        return array_map(fn($condition) => $condition->getRight(), $this->data);
    }

    /**
     * Returns the flat list of values to bind to the query, in condition order.
     *
     * @return array The bind values (arrays flattened, booleans as int, literals skipped).
     */
    public function getBindValues(): array {
        return array_merge([], ...array_map(fn($condition) => $condition->getBindValues(), $this->data));
    }

    public function replaceAllLeftValues(array $values): ConditionList {
        for($i = 0; $i < count($this->data); $i++) {
           if(isset($values[$i])) $this->data[$i]->setLeft($values[$i]);
        }
        return $this;
    }

    /**
     * Adds a value to the ConditionList.
     *
     * @param Condition $condition The value to add.
     *
     * @return ConditionList The updated ConditionList.
     */
    public function add(Condition $condition): ConditionList {
        $this->data[] = $condition;
        return $this;
    }

    /**
     * Removes a value from the ConditionList by its index.
     *
     * @param int $index The index of the value to remove.
     *
     * @return ConditionList The updated ConditionList.
     */
    public function remove(int $index): ConditionList {
        unset($this->data[$index]);
        return $this;
    }

    #/ METHODS
    #----------------------------------------------------------------------
}