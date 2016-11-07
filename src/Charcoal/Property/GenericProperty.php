<?php

namespace Charcoal\Property;

// Dependencies from `PHP`
use \PDO;

// Intra-module (`charcoal-core`) dependencies
use \Charcoal\Property\AbstractProperty;

/**
 * The most basic (generic) property possible, from abstract.
 */
class GenericProperty extends AbstractProperty
{
    /**
     * @return string
     */
    public function type()
    {
        return 'generic';
    }

    /**
     * @return string
     */
    public function sqlExtra()
    {
        return '';
    }

    /**
     * @return string
     */
    public function sqlType()
    {
        if ($this->multiple()) {
            return 'TEXT';
        } else {
            return 'VARCHAR(255)';
        }
    }

    /**
     * @return integer
     */
    public function sqlPdoType()
    {
        return PDO::PARAM_STR;
    }
}
