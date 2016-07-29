<?php

namespace Charcoal\Property;

use \Exception;
use \InvalidArgumentException;
use \JsonSerializable;
use \Serializable;

// Dependencies from PSR-3 (Logger)
use \Psr\Log\LoggerAwareInterface;
use \Psr\Log\LoggerAwareTrait;
use \Psr\Log\NullLogger;

// Dependencies from 'pimple`'
use \Pimple\Container;

// Dependencies from 'charcoal-config'
use \Charcoal\Config\AbstractEntity;

// Dependencies from 'charcoal-core'
use \Charcoal\Model\DescribableInterface;
use \Charcoal\Model\DescribableTrait;
use \Charcoal\Validator\ValidatableInterface;
use \Charcoal\Validator\ValidatableTrait;

// Dependency from 'charcoal-translation'
use \Charcoal\Translation\TranslationString;

// Local namespace dependencies
use \Charcoal\Property\DescribablePropertyInterface;
use \Charcoal\Property\DescribablePropertyTrait;
use \Charcoal\Property\PropertyInterface;
use \Charcoal\Property\PropertyValidator;
use \Charcoal\Property\StorablePropertyInterface;
use \Charcoal\Property\StorablePropertyTrait;

/**
 * An abstract class that implements the full `PropertyInterface`.
 */
abstract class AbstractProperty extends AbstractEntity implements
    JsonSerializable,
    Serializable,
    PropertyInterface,
    DescribableInterface,
    DescribablePropertyInterface,
    LoggerAwareInterface,
    StorablePropertyInterface,
    ValidatableInterface
{
    use LoggerAwareTrait;
    use DescribableTrait;
    use DescribablePropertyTrait;
    use StorablePropertyTrait;
    use ValidatableTrait;

    /**
     * @var string $ident
     */
    private $ident = '';

    /**
     * @var mixed $Val
     */
    protected $val;

    /**
     * @var TranslationString $label
     */
    private $label;

    /**
     * @var boolean $l10n
     */
    private $l10n = false;

    /**
     * @var boolean $hidden;
     */
    private $hidden = false;

    /**
     * @var boolean $multiple
     */
    private $multiple = false;

    /**
     * Array of options for multiple properties
     * - `separator` (default=",") How the values will be separated in the storage (sql).
     * - `min` (default=null) The min number of values. If null, <0 or NaN, then this is not taken into consideration.
     * - `max` (default=null) The max number of values. If null, <0 or NaN, then there is not limit.
     * @var mixed $multipleOptions
     */
    private $multipleOptions;

    /**
     * If true, this property *must* have a value
     * @var boolean $required
     */
    private $required = false;

    /**
     * Unique properties should not share he same value across 2 objects
     * @var boolean $unique
     */
    private $unique = false;

    /**
     * @var boolean $allowNull
     */
    private $allowNull = true;

    /**
     * Only the storable properties should be saved in storage.
     * @var boolean $storable
     */
    private $storable = true;

    /**
     * Inactive properties should be hidden everywhere / unused
     * @var boolean $active
     */
    private $active = true;

    /**
     * @var TranslationString $description
     */
    private $description = '';

    /**
     * @var TranslationString $_notes
     */
    private $notes = '';

    /**
     * @var array $viewOptions
     */
    protected $viewOptions;

    /**
     * @var string $displayType
     */
    protected $displayType;

    /**
     * Required dependencies:
     * - `logger` a PSR3-compliant logger.
     *
     * @param array $data Optional. Class Dependencies.
     */
    public function __construct(array $data = null)
    {
        if (!isset($data['logger'])) {
            $data['logger'] = new NullLogger();
        }
        $this->setLogger($data['logger']);

        // Optional DescribableInterface dependencies
        if (isset($data['property_factory'])) {
            $this->setPropertyFactory($data['property_factory']);
        }

        if (isset($data['metadata_loader'])) {
            $this->setMetadataLoader($data['metadata_loader']);
        }

        // DI Container can optionally be set in property constructor.
        if (isset($data['container'])) {
            $this->setDependencies($data['container']);
        }
    }

    /**
     * @param Container $container A Pimple DI container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        // This method is a stub. Reimplement in children class.
        $this->setPropertyFactory($container['property/factory']);
        $this->setMetadataLoader($container['metadata/loader']);
    }

    /**
     *
     *
     * @return string
     */
    public function __toString()
    {
        $val = $this->val();
        if (is_string($val)) {
            return $val;
        } else {
            if (is_object($val)) {
                return (string)$val;
            } else {
                return '';
            }
        }
    }

    /**
     * Get the "property type" string.
     *
     * ## Notes
     * - Type can not be set, so it must be explicitely provided by each implementing property classes.
     *
     * @return string
     */
    abstract public function type();

    /**
     * @param string $ident The property identifier.
     * @throws InvalidArgumentException  If the ident parameter is not a string.
     * @return PropertyInterface Chainable
     */
    public function setIdent($ident)
    {
        if (!is_string($ident)) {
            throw new InvalidArgumentException(
                'Ident needs to be string.'
            );
        }
        $this->ident = $ident;
        return $this;
    }

    /**
     * @throws Exception If trying to access getter before setter.
     * @return string
     */
    public function ident()
    {
        return $this->ident;
    }

    /**
     * Set the property's value.
     *
     * @param  mixed $val The property (raw) value.
     * @throws InvalidArgumentException If the value is invalid (NULL or not multiple when supposed to).
     * @return PropertyInterface Chainable
     */
    public function setVal($val)
    {
        if ($this->allowNull()) {
            if ($val === null || $val === '') {
                $this->val = null;

                return $this;
            }
        } elseif ($val === null) {
            throw new InvalidArgumentException(
                sprintf('Property "%s" value can not be NULL (not allowed)', $this->ident())
            );
        }

        if ($this->multiple()) {
            if (is_string($val)) {
                $val = explode($this->multipleSeparator(), $val);
            }

            if (!is_array($val)) {
                throw new InvalidArgumentException(
                    'Value is multiple. It must be a string (convertable to array by separator) or an array'
                );
            }
        }

        $this->val = $val;

        return $this;
    }

    /**
     * Retrieve the property's value.
     *
     * @return mixed
     */
    public function val()
    {
        return $this->val;
    }

    /**
     * Parse the given value.
     *
     * Note: the method returns the current value intact. Other properties can use this method to parse their values,
     * such as {@see \Charcoal\Property\ObjectProperty::parseVal()} who could parse objects into object IDs.
     *
     * @param  mixed $val A value to be parsed.
     * @return mixed Returns the parsed value.
     */
    public function parseVal($val = null)
    {
        return $val;
    }

    /**
     * @param   mixed $val     Optional. The value to to convert for input.
     * @param   array $options Optional input options.
     * @return  string
     */
    public function inputVal($val = null, array $options = [])
    {
        if ($val === null) {
            $val = $this->val();
        }

        if ($val === null) {
            return '';
        }

        /** Parse multilingual values */
        if ($this->l10n()) {
            $lang = isset($options['lang']) ? $options['lang'] : '';

            if (isset($val[$lang])) {
                $val = $val[$lang];
            } else {
                $val = '';
            }
        } elseif ($val instanceof TranslationString) {
            $val = (string)$val;
        }

        /** Parse multiple values / ensure they are of array type. */
        if ($this->multiple()) {
            if (is_array($val)) {
                $val = implode($this->multipleSeparator(), $val);
            }
        }

        if (!is_scalar($val)) {
            $val = json_encode($val, JSON_PRETTY_PRINT);
        }

        return $val;
    }

    /**
     * @param  mixed $val     Optional. The value to to convert for display.
     * @param  array $options Optional display options.
     * @return string
     */
    public function displayVal($val = null, array $options = [])
    {
        if ($val === null) {
            $val = $this->val();
        }

        if ($val === null) {
            return '';
        }

        $propertyValue = $val;

        if ($this->l10n()) {
            $lang = isset($options['lang']) ? $options['lang'] : '';
            $propertyValue = (isset($propertyValue[$lang]) ? $propertyValue[$lang] : '');
        }

        if ($this->multiple()) {
            if (is_array($propertyValue)) {
                $propertyValue = implode($this->multipleSeparator(), $propertyValue);
            }
        }
        return (string)$propertyValue;
    }

    /**
     * @param mixed $label The property label.
     * @return PropertyInterface Chainable
     */
    public function setLabel($label)
    {
        $this->label = new TranslationString($label);
        return $this;
    }

    /**
     * @return string
     */
    public function label()
    {
        if ($this->label === null) {
            return ucwords(str_replace(['.', '_'], ' ', $this->ident()));
        }
        return $this->label;
    }

    /**
     * @param boolean $l10n The l10n, or "translatable" flag.
     * @return PropertyInterface Chainable
     */
    public function setL10n($l10n)
    {
        $this->l10n = !!$l10n;
        return $this;
    }

    /**
     * The l10n flag sets the property as being translatable, meaning the data is held for multple languages.
     *
     * @return boolean
     */
    public function l10n()
    {
        return $this->l10n;
    }

    /**
     * @param boolean $hidden The hidden flag.
     * @return PropertyInterface Chainable
     */
    public function setHidden($hidden)
    {
        $this->hidden = !!$hidden;
        return $this;
    }

    /**
     * @return boolean
     */
    public function hidden()
    {
        return $this->hidden;
    }

    /**
     * @param boolean $multiple The multiple flag.
     * @return PropertyInterface Chainable
     */
    public function setMultiple($multiple)
    {
        $this->multiple = !!$multiple;
        return $this;
    }

    /**
     * The multiple flags sets the property as being "repeatable", or allow to represent an array of multiple values.
     *
     * ## Notes
     * - The multiple flag can be forced to false (or true) in implementing property class.
     * - How a multiple behaves also depend on `multipleOptions`.
     *
     * @return boolean
     */
    public function multiple()
    {
        return $this->multiple;
    }

    /**
     * Set the multiple options / configuration, when property is `multiple`.
     *
     * ## Options structure
     * - `separator` (string) The separator charactor.
     * - `min` (integer) The minimum number of values. (0 = no limit).
     * - `max` (integer) The maximum number of values. (0 = no limit).
     *
     * @param array $multipleOptions The property multiple options.
     * @return PropertyInterface Chainable
     */
    public function setMultipleOptions(array $multipleOptions)
    {
        // The options are always merged with the defaults, to ensure minimum required array structure.
        $options = array_merge($this->defaultMultipleOptions(), $multipleOptions);
        $this->multipleOptions = $options;
        return $this;
    }

    /**
     * The options defining the property behavior when the multiple flag is set to true.
     *
     * @return array
     * @see self::defaultMultipleOptions
     */
    public function multipleOptions()
    {
        if ($this->multipleOptions === null) {
            return $this->defaultMultipleOptions();
        }
        return $this->multipleOptions;
    }

    /**
     * @return array
     */
    public function defaultMultipleOptions()
    {
        return [
            'separator' => ',',
            'min'       => 0,
            'max'       => 0
        ];
    }

    /**
     * @return string
     */
    public function multipleSeparator()
    {
        $multipleOptions = $this->multipleOptions();
        return $multipleOptions['separator'];
    }

    /**
     * @param boolean $allow The property allow null flag.
     * @return PropertyInterface Chainable
     */
    public function setAllowNull($allow)
    {
        $this->allowNull = !!$allow;
        return $this;
    }

    /**
     * The allow null flag sets the property as being able to be of a "null" value.
     *
     * ## Notes
     * - This flag typically modifies the storage database to also allow null values.
     *
     * @return boolean
     */
    public function allowNull()
    {
        return $this->allowNull;
    }

    /**
     * @param boolean $required The property required flag.
     * @return PropertyInterface Chainable
     */
    public function setRequired($required)
    {
        $this->required = !!$required;
        return $this;
    }

    /**
     * Required flag sets the property as being required, meaning not allowed to be null / empty.
     *
     * ## Notes
     * - The actual meaning of "required" might be different for implementing property class.
     *
     * @return boolean
     */
    public function required()
    {
        return $this->required;
    }

    /**
     * @param boolean $unique The property unique flag.
     * @return PropertyInterface Chainable
     */
    public function setUnique($unique)
    {
        $this->unique = !!$unique;
        return $this;
    }

    /**
     * @return boolean
     */
    public function unique()
    {
        return $this->unique;
    }

    /**
     * @param boolean $active The property active flag. Inactive properties should have no effects.
     * @return PropertyInterface Chainable
     */
    public function setActive($active)
    {
        $this->active = !!$active;
        return $this;
    }

    /**
     * @return boolean
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * @param boolean $storable The storable flag.
     * @return PropertyInterface Chainable
     */
    public function setStorable($storable)
    {
        $this->storable = !!$storable;
        return $this;
    }

    /**
     * @return boolean
     */
    public function storable()
    {
        return $this->storable;
    }

    /**
     * @param mixed $description The property description.
     * @return PropertyInterface Chainable
     */
    public function setDescription($description)
    {
        $this->description = new TranslationString($description);
        return $this;
    }

    /**
     * @return TranslationString
     */
    public function description()
    {
        return $this->description;
    }

    /**
     * @param mixed $notes The property notes.
     * @return PropertyInterface Chainable
     */
    public function setNotes($notes)
    {
        $this->notes = new TranslationString($notes);
        return $this;
    }

    /**
     * @return TranslationString
     */
    public function notes()
    {
        return $this->notes;
    }



    /**
     * The property's default validation methods/
     *
     * - `required`
     * - `unique`
     * - `allowNull`
     *
     * ## Notes
     * - Those 3 base validation methods should always be merged, in implementing factory class.
     *
     * @return array
     */
    public function validationMethods()
    {
        return [
            'required',
            'unique',
            'allowNull'
        ];
    }

    /**
     * @return boolean
     */
    public function validateRequired()
    {
        if ($this->required() && !$this->val()) {
            $this->validator()->error('Value is required.', 'required');
            return false;
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function validateUnique()
    {
        if (!$this->unique()) {
            return true;
        }

        /** @todo Check in the model's storage if the value already exists. */
        return true;
    }

    /**
     * @return boolean
     */
    public function validateAllowNull()
    {
        if (!$this->allowNull() && $this->val() === null) {
            $this->validator()->error('Value can not be null.', 'allowNull');
            return false;
        }
        return true;
    }

    /**
     * @param string $propertyIdent The ident of the property to retrieve.
     * @return mixed
     */
    protected function propertyValue($propertyIdent)
    {
        if (isset($this->{$propertyIdent})) {
            return $this->{$propertyIdent};
        } else {
            return null;
        }
    }

    /**
     * @param array $data Optional. Metadata data.
     * @return PropertyMetadata
     */
    protected function createMetadata(array $data = null)
    {
        $metadata = new PropertyMetadata();
        if (is_array($data)) {
            $metadata->setData($data);
        }
        return $metadata;
    }

    /**
     * ValidatableTrait > createValidator(). Create a Validator object
     *
     * @return ValidatorInterface
     */
    protected function createValidator()
    {
        $validator = new PropertyValidator($this);
        return $validator;
    }

    /**
     * @return mixed
     */
    abstract public function save();

    /**
     * Serializable > serialize()
     *
     * @return string
     */
    public function serialize()
    {
        $data = $this->val();
        return serialize($data);
    }
    /**
     * Serializable > unsierialize()
     *
     * @param string $data Serialized data.
     * @return void
     */
    public function unserialize($data)
    {
        $data = unserialize($data);
        $this->setVal($data);
    }

    /**
     * JsonSerializable > jsonSerialize()
     *
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->val();
    }

    /**
     * @param string $type The display type.
     * @return PropertyInterface Chainable
     */
    public function setDisplayType($type)
    {
        $this->displayType = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function displayType()
    {
        if (!$this->displayType) {
            $meta = $this->metadata();

            // text display outputs the val as text
            $default = 'charcoal/admin/property/display/text';

            // This default would be defined in type-property.json (@see charcoal-property/metadata)
            if (isset($meta['admin']) && isset($meta['admin']['display_type'])) {
                $default = $meta['admin']['display_type'];
            }
            $this->setDisplayType($default);
        }
        return $this->displayType;
    }

    /**
     * View options.
     * @param string $ident The display ident (ex: charcoal/admin/property/display/text).
     * @return array Should ALWAYS be an array.
     */
    public function viewOptions($ident = null)
    {
        // No options defined
        if (!$this->viewOptions) {
            return [];
        }

        // No ident defined
        if (!$ident) {
            return $this->viewOptions;
        }

        // Invalid ident
        if (!isset($this->viewOptions[$ident])) {
            return [];
        }

        // Success!
        return $this->viewOptions[$ident];
    }

    /**
     * Set view options for both display and input
     *
     * @param array $viewOpts View options.
     * @return PropertyInterface Chainable
     */
    public function setViewOptions(array $viewOpts = [])
    {
        $this->viewOptions = $viewOpts;
        return $this;
    }
}