<?php

define_unless('LOG_EMAILS', false);
define_unless('SEND_EMAILS', true);

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Mail {

    public function __construct() {

        Octopus::loadExternal('htmlmimemail');

        $this->mailHandler = new htmlMimeMail();

        $this->to = array();
        $this->from = '';
        $this->replyTo = '';
        $this->subject = '';
        $this->text = '';
        $this->html = '';

        $this->files = array();
    }

    function to($to) {
        if (is_array($to)) {
            $this->to = array_merge($this->to, $to);
        } else {
            $this->to[] = $to;
        }
    }

    function from($from) {
        $this->from = $from;
    }

    function replyTo($reply) {
        $this->replyTo = $reply;
    }

    function subject($subject) {
        $this->subject = $subject;
    }

    function text($text) {
        $this->text = $text;
    }

    function html($html) {
        $this->html = $html;
    }

    function attach($file) {
        if (is_file($file)) {
            $this->files[] = $file;
        }
    }

    function send() {
        if (LOG_EMAILS) {
            $this->sendTest();
        }

        if (SEND_EMAILS) {
            if ($this->sendReal()) {
                return true;
            } else {
                $this->_unknownError();
                return false;
            }
        }
    }

    function sendTest() {

        $to = implode(', ', $this->to);

        $message = array(
            "from" => $this->from,
            "to" => implode(', ', $this->to),
        );

       if ($this->replyTo) {
            $message["reply-to"] = $this->replyTo;
        }

        $message['subject'] = $this->subject;


        if ($this->text != '') {
            $message['body_text'] = $this->text;
        }

        if ($this->html != '') {
            $message['body_html'] = $this->html;
        }

        $message['files'] = array_map('basename', $this->files);

        $dir = get_option('LOG_DIR');
        if (!$dir) $dir = get_option('OCTOPUS_PRIVATE_DIR');
        $log = new Octopus_Logger_File($dir . 'emails.log');
        $log->log($message);

    }

    function sendReal() {

        $this->mailHandler->setTextCharset('UTF-8');
        $this->mailHandler->setHtmlCharset('UTF-8');
        $this->mailHandler->setHeadCharset('UTF-8');

        if ($this->html != '') {
            $this->mailHandler->setHtml($this->html, $this->text);
        } else {
            $this->mailHandler->setText($this->text);
        }

        if (!empty($this->replyTo)) {
            $this->mailHandler->setHeader('Reply-To', $this->replyTo);
        } else {
            $this->mailHandler->setHeader('Reply-To', $this->from);
        }

        // MH: In safe mode, calling setReturnPath makes htmlMimeMail crap out
        if (ini_get("safe_mode") < 1) {
            $this->mailHandler->setReturnPath($this->from);
        }

        $this->mailHandler->setFrom($this->from);
        $this->mailHandler->setSubject($this->subject);
        $this->mailHandler->setHeader('Date', date(DATE_RFC822));

        foreach ($this->files as $file) {
            $this->mailHandler->addAttachment($file);
        }

        $send_type = 'mail';

        $checkSpecific = false;
        if (defined('MAIL_SPECIFIC_DOMAIN') && count($this->to) == 1) {
            if (preg_match('/@(.*)$/', $this->to[0], $matches)) {
                if ($matches[1] == MAIL_SPECIFIC_DOMAIN) {
                    $checkSpecific = true;
                }
            }
        }

        if (defined('MAIL_SPECIFIC_SMTP_HOST') && $checkSpecific) {
            $this->mailHandler->setSMTPParams(
                MAIL_SPECIFIC_SMTP_HOST,
                defined('MAIL_SMTP_PORT') ? MAIL_SMTP_PORT : 25,
                MAIL_SPECIFIC_SMTP_HOST,
                defined('MAIL_SMTP_USERNAME'),
                defined('MAIL_SMTP_USERNAME') ? MAIL_SMTP_USERNAME : null,
                defined('MAIL_SMTP_PASSWORD') ? MAIL_SMTP_PASSWORD : null
                );

            $send_type = 'smtp';
        } else if (defined('MAIL_SMTP_HOST')) {
            $this->mailHandler->setSMTPParams(
                MAIL_SMTP_HOST,
                defined('MAIL_SMTP_PORT') ? MAIL_SMTP_PORT : 25,
                MAIL_SMTP_HOST,
                defined('MAIL_SMTP_USERNAME'),
                defined('MAIL_SMTP_USERNAME') ? MAIL_SMTP_USERNAME : null,
                defined('MAIL_SMTP_PASSWORD') ? MAIL_SMTP_PASSWORD : null
                );

            $send_type = 'smtp';
        }

        if ($this->mailHandler->send($this->to, $send_type)) {
            return true;
        } else {

            if (isset($this->mailHandler->errors)) {
                $this->_log(sprintf('mail error: %s', implode(', ', $this->mailHandler->errors)));
            } else {
                $this->_unknownError();
            }

            return false;
        }
    }

    function _log($message) {
        $log = new Octopus_Logger_File(LOG_DIR . 'mail.log');
        $log->log($message);
    }

    function _unknownError() {
        $to = '[Empty To: List]';

        if (!empty($this->to)) {

            if (is_array($this->to))
                $to = implode(', ', $this->to);
            else if (is_string($this->to))
                $to = $this->to;

        }

        $this->_log(sprintf('Unknown error sending mail to %s', $to));

    }
}

