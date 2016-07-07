<?php

namespace Charcoal\Property;

// Dependencies from `PHP` extensions
use \PDO;

// Module `charcoal-core` dependencies
use \Charcoal\Property\AbstractProperty;

/**
 * Number Property
 */
class NumberProperty extends AbstractProperty
{
    /**
     * @return string
     */
    public function type()
    {
        return 'number';
    }

    /**
     * @return string
     */
    public function sqlExtra()
    {
        return '';
    }

    /**
     * Get the SQL type (Storage format)
     *
     * Stored as `VARCHAR` for max_length under 255 and `TEXT` for other, longer strings
     *
     * @return string The SQL type
     */
    public function sqlType()
    {
        // Multiple number are stocked as TEXT because we do not know the maximum length
        if ($this->multiple()) {
            return 'TEXT';
        }

        return 'DOUBLE';
    }

    /**
     * @return integer
     */
    public function sqlPdoType()
    {
        return PDO::PARAM_STR;
    }

    /**
     * @return mixed
     */
    public function save()
    {
        return $this->val();
    }
}