<?php

SG::loadClass('SG_Html_Element');

class SG_Html_Form_Field extends SG_Html_Element {

    private static $_registry = array();

    public $label;
    public $help;

    public function __construct($name, $type, $attributes) {

        parent::__construct('input', $attributes);

        $this->type = $type;
        $this->name = $name;
        $this->id = $name . 'Input';

        $this->addClass(to_css_class($name))
             ->addClass(to_css_class($type));

        $this->label = $this->createLabel();

    }

    /**
     * Sets the 'autofocus' attribute on this element.
     */
    public function autoFocus($focus = true) {

        if ($focus) {
            $this->setAttribute('autofocus', '');
        } else {
            $this->removeAttribute('autofocus');
        }

    }

    /**
     * Helper function for setting the 'value' attribute.
     * @return Object $this for method chaining.
     */
    public function &val(/* No args = return val, 1 arg = set value */) {

        $argCount = func_num_args();

        switch($argCount) {

            case 0:
                $val = $this->getAttribute('value');
                return $val;

            default:
                $this->setAttribute('value', func_get_arg(0));
                return $this;
        }
    }

    /**
     * Creates the <label> element used by this field.
     */
    protected function createLabel() {

        $label = new SG_Html_Element('label', array('for' => $this->name));
        $label->text(humanize($this->name) . ':');

        return $label;
    }

    /**
     * Registers a form field type.
     * @param $name String The unique name of this field type.
     * @param $class String The name of the class used for this field type
     * @param $attributes Array Default attributes for this input type.
     * @param $tag String The tag this type uses, if $class is SG_Html_Element.
     */
    public static function register($name, $class, $attributes = null) {

        self::$_registry[$name] = array(
            'class' => $class,
            'attributes' => $attributes,
        );

    }

    /**
     * Factory method for creating new form fields.
     */
    public static function create($name, $type = null, $desc = null, $attributes = null) {

        if (is_array($desc) && $attributes === null) {
            $attributes = $desc;
            $desc = null;
        }

        if (is_array($type) && $desc === null && $attributes === null) {
            $attributes = $type;
            $type = empty($attributes['type']) ? 'text' : $attributes['type'];
            unset($attributes['type']);
        }

        $attributes = $attributes ? $attributes : array();

        if ($type === null) {
            $type = empty($attributes['type']) ? $name : $attributes['type'];
        }

        $class = 'SG_Html_Form_Field';

        if (isset(self::$_registry[$type])) {
            $entry = self::$_registry[$type];
            $class = empty($entry['class']) ? $class : $entry['class'];
            $attributes = array_merge(empty($entry['attributes']) ? array() : $entry['attributes'], $attributes);
        }


        SG::loadClass($class);

        return new $class($name, $type, $attributes);
    }

}

SG_Html_Form_Field::register('email', 'SG_Html_Form_Field', array('type' => 'email', 'class' => 'text'));

?>
