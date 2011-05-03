<?php

Octopus::loadClass('Octopus_Logger_File');

require_once(EXTERNAL_INCLUDE_DIR . 'htmlMimeMail/htmlMimeMail.php');

define_unless('LOG_EMAILS', false);
define_unless('SEND_EMAILS', true);

//MH: PHP 4 doesn't know this.
define_unless('DATE_RFC822', 'D, d M y H:i:s O');


class Octopus_Mail {

    function Octopus_Mail() {

        $this->mailHandler = new htmlMimeMail();

        $this->to = array();
        $this->from = '';
        $this->replyTo = '';
        $this->subject = '';
        $this->text = '';
        $this->html = '';

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

        $date = date('D, d M Y H:i:s O');
        $output = str_repeat('=', 80);

        $reply = '';
        if ($this->replyTo != '') {
            $reply = "Reply-To: {$this->replyTo}\n";
        }


        $output .= <<<END

TEST EMAIL OUTPUT ($date):
To: $to
From: {$this->from}
{$reply}Subject: {$this->subject}

END;

        if ($this->text != '') {
            $output .= "Text Contents:\n\n{$this->text}\n\n";
        }

        if ($this->html != '') {
            $output .= "Html Contents:\n\n{$this->html}\n\n";
        }

        $fp = fopen('/tmp/sole_email_test.log', 'a');
        fwrite($fp, $output);
        fclose($fp);

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

        // MH: In safe mode, calling setReturnPath makes htmlMimeMail crap out
        if (ini_get("safe_mode") < 1) {

            $this->mailHandler->setReturnPath($this->from);

            if (!empty($this->replyTo)) {
                $this->mailHandler->setHeader('Reply-To', $this->replyTo);
                $this->mailHandler->setReturnPath($this->replyTo);
            } else {
                $this->mailHandler->setHeader('Reply-To', $this->from);
                $this->mailHandler->setReturnPath($this->from);
            }
        }

        $this->mailHandler->setFrom($this->from);
        $this->mailHandler->setSubject($this->subject);
        $this->mailHandler->setHeader('Date', date(DATE_RFC822));

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

?>
