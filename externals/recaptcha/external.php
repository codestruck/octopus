<?php

    require_once(dirname(__FILE__) . '/recaptchalib.php');

/**
 * Central configuration point for using Recaptcha in Octopus apps. Provides
 * methods to set/get public and private recaptcha keys.
 */
class Octopus_Recaptcha {

    /**
     * Default public recaptcha key used if none is specified. This is a
     * key that is configured to work across all domains, but you should
     * really set up site-specific keys.
     */
    const DEFAULT_PUBLIC_KEY = '6LfB7tYSAAAAADef0otS4oVsiVvHH0QzX-LgYYl5';

    /**
     * Default private recaptcha key used if none is specified. This is a
     * key that is configured to work across all domains, but you should
     * really set up site-specific keys.
     */
    const DEFAULT_PRIVATE_KEY = '6LfB7tYSAAAAAG6eTdv5o5IdBwVlr3DLAT9Ys6vK';

    private static $privateKey = self::DEFAULT_PRIVATE_KEY;
    private static $publicKey = self::DEFAULT_PUBLIC_KEY;

    private function __construct() { }

    /**
     * @return String The default recaptcha private key to use.
     */
    public static function getPrivateKey() {
        return self::$privateKey;
    }

    /**
     * @return String The default recaptcha public key to use.
     */
    public static function getPublicKey() {
        return self::$publicKey;
    }

    /**
     * Sets the private recaptcha key to use.
     * @param String $key
     */
    public function setPrivateKey($key) {
        self::$privateKey = $key;
    }

    /**
     * Sets the public recaptcha key to use.
     * @param String $key
     */
    public function setPublicKey($key) {
        self::$publicKey = $key;
    }

}

/**
 * Form field that renders a recaptcha and requires valid entry.
 * Usage:
 *
 * $form->add('recaptcha')
 *
 * A validation rule for the captcha is automatically added to the form.
 *
 */
class Octopus_Html_Form_Field_Recaptcha extends Octopus_Html_Form_Field {

    private $publicKey = null;
    private $privateKey = null;
    private $challenge = '';
    private $response = '';
    private $failureMessage = 'Please re-enter the captcha text.';

    public function __construct($type, $name, $label, $attributes) {

        parent::__construct('span', $type, $name, $label, $attributes);

        // do validation by default
        $this->mustPass(array($this, 'validateCaptcha'));
        $this->addClass('required');

    }

    public function getAttribute($name, $default = null) {

        if ($name === 'value') {
            return array('challenge' => $this->challenge, 'response' => $this->response);
        }

        return parent::getAttribute($name, $default);

    }

    /**
     * @return String The recaptcha challenge value.
     */
    public function getChallenge() {
        return $this->challenge;
    }

    /**
     * @return String Message to display when captcha validation fails.
     */
    public function getFailureMessage() {
        return $this->failureMessage;
    }

    /**
     * @return String The private key this field is configured to use. If none
     * has been specified, {@link self::DEFAULT_PRIVATE_KEY} is returned.
     */
    public function getPrivateKey() {
        return $this->privateKey === null ? Octopus_Recaptcha::getPrivateKey() : $this->privateKey;
    }

    /**
     * @return String The public key this field is configured to use. If none
     * has been specified, {@link self::DEFAULT_PUBLIC_KEY} is returned.
     */
    public function getPublicKey() {
        return $this->publicKey === null ? Octopus_Recaptcha::getPublicKey() : $this->publicKey;
    }

    /**
     * @return String The recaptcha response value.
     */
    public function getResponse() {
        return $this->response;
    }

    public function loadValue(&$values) {

        $this->challenge = isset($values['recaptcha_challenge_field']) ? $values['recaptcha_challenge_field'] : '';
        $this->response =  isset($values['recaptcha_response_field'])  ? $values['recaptcha_response_field']  : '';

    }

    public function setAttribute($name, $value) {

        if ($name === 'value') {

            if (is_array($value)) {
                $this->challenge = isset($value['challenge']) ? $value['challenge'] : '';
                $this->response = isset($value['response']) ? $value['response'] : '';
            } else {
                $this->challenge = $this->response = '';
            }

            return $this;

        }

        return parent::setAttribute($name, $value);

    }

    /**
     * Sets the message to be displayed when captcha validation fails.
     * @param String $message
     */
    public function setFailureMessage($message) {
        $this->failureMessage = $message;
        return $this;
    }

    public function renderContent($escape = Octopus_Html_Element::ESCAPE_ATTRIBUTES) {
        return recaptcha_get_html($this->getPublicKey(), null, true);
    }

    public function validateCaptcha($value) {

        if (!is_array($value) || empty($value['challenge']) || empty($value['response'])) {
            return $this->getFailureMessage();
        }

        $resp = recaptcha_check_answer($this->getPrivateKey(), get_user_ip(), $value['challenge'], $value['response']);
        return $resp && $resp->is_valid ? true : $this->getFailureMessage();

    }

}

Octopus_Html_Form_Field::register('recaptcha', 'Octopus_Html_Form_Field_Recaptcha');