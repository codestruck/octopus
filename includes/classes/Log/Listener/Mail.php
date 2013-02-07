<?php

/**
 * A log listener that fires off emails.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Log_Listener_Mail {

    private $to = array();

    public function __construct($to = null) {

        foreach(func_get_args() as $arg) {
            $this->addRecipient($arg);
        }

    }

    public function addRecipient($addr) {

        if (!is_array($addr)) {
            return $this->addRecipient(explode(',', $addr));
        }

        foreach($addr as $to) {
            $to = trim($to);
            $this->to[$to] = true;
        }

    }

    public function write($id, $message, $log, $level) {

        if (class_exists('Octopus_Mail')) {
            $this->writeUsingOctopusMail($message, $log, $level);
        } else {
            // TODO: native php mail()
        }

    }

    private function writeUsingOctopusMail($message, $log, $level) {

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : trim(`hostname`);

        $console = new Octopus_Log_Listener_Console(false);
        $console->renderInColor = false;
        $console->stackTraceLines = -1; // full stack trace

        $body = $console->formatForDisplay($message, $log, $level);
        $subject = Octopus_Log::getLevelName($level) . ' in ' . $log . ' on ' . $host;

        $htmlBody = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
        $htmlBody = nl2br($htmlBody);
        $htmlBody = "<pre>{$htmlBody}</pre>";

        $mail = new Octopus_Mail();
        $mail->to(array_keys($this->to));
        $mail->subject($subject);
        $mail->text($body);
        $mail->html($htmlBody);
        $mail->from(Octopus_Log::getLevelName($level) . '@' . $_SERVER['HTTP_HOST']);
        $mail->send();

    }
}
