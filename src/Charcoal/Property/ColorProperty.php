<?php

namespace Charcoal\Property;

// Dependencies from `PHP`
use \Exception;
use \InvalidArgumentException;

// Dependencies from `PHP` extensions
use \PDO;

// Intra module (`charcoal-property`) dependencies
use \Charcoal\Property\AbstractProperty;

/**
 * Color Property
 */
class ColorProperty extends AbstractProperty
{
    /**
     * @var boolean $supportAlpha
     */
    private $supportAlpha = false;

    /**
     * @return string
     */
    public function type()
    {
        return 'color';
    }

    /**
     * @param boolean $support The alpha support flag.
     * @return ColorProperty Chainable
     */
    public function setSupportAlpha($support)
    {
        $this->supportAlpha = !!$support;
        return $this;
    }

    /**
     * @return boolean
     */
    public function supportAlpha()
    {
        return $this->supportAlpha;
    }

    /**
     * AbstractProperty > setVal(). Ensure proper hexadecimal value.
     *
     * @param mixed $val The value to set.
     * @return ColorProperty Chainable
     */
    public function setVal($val)
    {
        if ($val === null) {
            if ($this->allowNull()) {
                $this->val = null;
                return $this;
            } else {
                throw new InvalidArgumentException(
                    'Val can not be null (Not allowed)'
                );
            }
        }
        if ($this->multiple()) {
            if (is_string($val)) {
                $val = explode($this->multipleSeparator(), $val);
            }
            if (!is_array($val)) {
                throw new InvalidArgumentException(
                    'Val is multiple so it must be a string (convertable to array by separator) or an array'
                );
            }
            $ret = [];
            foreach($val as $v) {
                $ret[] = $this->colorVal($v);
            }
            $this->val = $ret;
        } else {
            $this->val = $this->colorVal($val);
        }
        return $this;
    }

    /**
     * @param string|array $val The color value to sanitize to an hexadecimal or rgba() value.
     * @return string The color string. Hexadecimal or rgba() if alpha is supported..
     */
    public function colorVal($val)
    {
        if (!$val) {
            return $val;
        }
        $parsed = $this->parseVal($val);
        if (!$this->supportAlpha()) {
            return '#'.$this->rgbToHexadecimal($parsed['r'], $parsed['g'], $parsed['b']);
        } else {
            return sprintf(
                'rgba(%d,%d,%d,%s)',
                $parsed['r'],
                $parsed['g'],
                $parsed['b'],
                $parsed['a']
            );
        }
    }

    /**
     * @param integer $r Red value (0 to 255).
     * @param integer $g Green value (0 to 255).
     * @param integer $b Blue value (0 to 255).
     * @return string Hexadecimal color value, as uppercased hexadecimal without the "#" prefix.
     */
    protected function rgbToHexadecimal($r, $g, $b)
    {
        $hex = '';
        $hex .= str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
        $hex .= str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
        $hex .= str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
        return strtoupper($hex);
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
     * Stored as `VARCHAR` for maxLength under 255 and `TEXT` for other, longer strings
     *
     * @return string The SQL type
     */
    public function sqlType()
    {
        // Multiple strings are always stored as TEXT because they can hold multiple values
        if ($this->multiple()) {
            return 'TEXT';
        }

        if ($this->supportAlpha()) {
            return 'VARCHAR(32)';
        } else {
            return 'CHAR(7)';
        }

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

    /**
     * @param string|array $val The color array or string to parse.
     * @return array The parsed `[r,g,b,a]` color array.
     */
    private function parseVal($val)
    {
        if (is_array($val)) {
            return $this->parseArray($val);
        } else {
            return $this->parseString($val);
        }
    }

    /**
     * @param array $val The color array to parse.
     * @throws InvalidArgumentException If the array does not have at least 3 items.
     * @return array The parsed `[r,g,b,a]` color array.
     */
    private function parseArray(array $val)
    {
        if (count($val) < 3) {
            throw new InvalidArgumentException(
                'Color value must have at least 3 items in array (r, g and b) to be parsed by parseArray()'
            );
        }
        if (isset($val['r'])) {
            $r = $val['r'];
            $g = $val['g'];
            $b = $val['b'];
            $a = isset($val['a']) ? $val['a'] : 0;
        } else {
            $r = $val[0];
            $g = $val[1];
            $b = $val[2];
            $a = isset($val[3]) ? $val[3] : 0;
        }

        return [
            'r' => (int)$r,
            'g' => (int)$g,
            'b' => (int)$b,
            'a' => $a
        ];
    }

    /**
     * @param string $val The colors string to parse.
     * @throws InvalidArgumentException If the color value is not a string.
     * @return array The parsed `[r,g,b,a]` color array.
     */
    private function parseString($val)
    {
        if (!is_string($val)) {
            throw new InvalidArgumentException(
                'Color value must be a sting to be parsed by parseString()'
            );
        }

        $val = str_replace('#', '', strtolower($val));
        if (ctype_xdigit($val)) {
            return $this->parseHexadecimal($val);
        } elseif (strstr($val, 'rgb(')) {
            return $this->parseRgb($val);
        } elseif (strstr($val, 'rgba(')) {
            return $this->parseRgba($val);
        } elseif (strstr($val, 'hsl(')) {
            return $this->parseHsl($val);
        } elseif (strstr($val, 'hsla(')) {
            return $this->parseHsla($val);
        } else {
            return $this->parseNamedColor($val);
        }
    }

    /**
     * @param string $val The hexadecimal color string to parse.
     * @return array The parsed `[r,g,b,a]` color array.
     */
    private function parseHexadecimal($val)
    {
        $val = str_replace('#', '', $val);

        if (strlen($val) == 3) {
            return [
                'r' => hexdec(substr($val, 0, 1).substr($val, 0, 1)),
                'g' => hexdec(substr($val, 1, 1).substr($val, 1, 1)),
                'b' => hexdec(substr($val, 2, 1).substr($val, 2, 1)),
                // Ignore alpha.
                'a' => 0
            ];
        } else {
            return [
                'r' => hexdec(substr($val, 0, 2)),
                'g' => hexdec(substr($val, 2, 2)),
                'b' => hexdec(substr($val, 4, 2)),
                // Ignore alpha.
                'a' => 0
            ];
        }
    }

    /**
     * @param string $val The rgb() color string to parse.
     * @return array The parsed `[r,g,b,a]` color array.
     */
    private function parseRgb($val)
    {
        $match = preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', $val, $m);
        if (!$match) {
            throw new InvalidArgumentException(
                'String does not match rgb() format to be parsed by parseRgb()'
            );
        }
        return [
            'r' => $m[1],
            'g' => $m[2],
            'b' => $m[3],
            // Ignore alpha.
            'a' => 0
        ];
    }

    /**
     * @param string $val The rgab() color string to parse.
     * @return array The parsed `[r,g,b,a]` color array.
     */
    private function parseRgba($val)
    {
        $match = preg_match('/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\(\d+)\s*\)/i', $val, $m);
        if (!$match) {
            throw new InvalidArgumentException(
                'String does not match rgba() format to be parsed by parseRgba()'
            );
        }
        return [
            'r' => (int)$m[1],
            'g' => (int)$m[2],
            'b' => (int)$m[3],
            'a' => $m[4]
        ];
    }

    /**
     * @param string $val The hsl() color string to parse.
     * @throws Exception This parse method is not yet supported.
     */
    private function parseHsl($val)
    {
        unset($val);
        throw new Exception(
            'HSL color value is not yet supported'
        );
    }

    /**
     * @param string $val The hsla() string color val to parse.
     * @throws Exception This parse method is not yet supported.
     */
    private function parseHsla($val)
    {
        unset($val);
        throw new Exception(
            'HSL color value is not yet supported'
        );
    }

    /**
     * @param string $val The named color val to parse.
     * @throws InvalidArgumentException If the string is not an existing SVG color.
     * @return array The parsed `[r,g,b,a]` color array.
     */
    private function parseNamedColor($val)
    {
        $colors = include 'data/colors.php';
        $val = strtolower($val);
        if (in_array($val, array_keys($colors))) {
            return $this->parseVal($colors[$val]);
        }

        throw new InvalidArgumentException(
            'Color "%s" is not a valid SVG (or CSS) color name.'
        );
    }
}