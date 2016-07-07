<?php

namespace Charcoal\Property;

// Intra-module (`charcoal-core`) dependencies
use Charcoal\Validator\AbstractValidator;

/**
 *
 */
class PropertyValidator extends AbstractValidator
{
    /**
     * @return boolean
     */
    public function validate()
    {
        // The model, in this case, should be a PropertyInterface
        $model = $this->model;

        $ret = true;
        $validationMethods = $model->validationMethods();
        foreach ($validationMethods as $m) {
            $fn = [$model, 'validate_'.$m];
            if (is_callable($fn)) {
                $ret = $ret && call_user_func($fn);
            }
        }
        return $ret;
    }
}