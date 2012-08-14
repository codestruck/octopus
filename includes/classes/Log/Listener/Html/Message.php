<?php

/**
 * Helper used to represent a single message being rendered as HTML.
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Log_Listener_Html_Message {

    public $title = '';

    private $sections = array();
    private $footer = array();
    private $classes = array();
    private $log;
    private $level;
    private static $nextID = 0;

    /**
     * Creates a new block with the given title.
     * @param String $title Title for this debug
     */
    public function __construct($log, $level) {
        $this->log = $log;
        $this->level = $level;

        $this->addClass('octopus-debug-level-' . strtolower(Octopus_Log::getLevelName($level)));
    }

    /**
     * Adds a named section to this message.
     * @param  String $name    Name of the section ('$_GET', 'Trace', etc)
     * @param  String $content HTML content for the section
     * @param  Array $alternateViews Key/value pairs for named alternate views
     * of the same content.
     */
    public function add($name, $content, $alternateViews = array()) {
        $this->sections[] = compact('name', 'content', 'alternateViews');
    }

    /**
     * Adds one or more CSS classes to this message.
     */
    public function addClass($class) {
        foreach(func_get_args() as $arg) {
            if (is_array($arg)) {
                call_user_func_array(array($this, 'addClass'), $arg);
            } else {
                $class = trim($arg);
                if ($class) $this->classes[$class] = true;
            }
        }
    }

    /**
     * Adds a named section to the footer of this message.
     */
    public function addFooterElement($name, $content) {
        $this->footer[] = compact('name', 'content');
    }

    /**
     * Renders this message.
     * @param  boolean $return If true, the HTML is returned. Otherwise it is
     * outputted directly.
     */
    public function render($return = false) {

        $classes = $this->classes;

        $log = htmlspecialchars($this->log, ENT_QUOTES, 'UTF-8');
        $level = Octopus_Log::getLevelName($this->level);

        $title = trim($this->title);
        $loc = '';
        if (!$title) {

            $lines = Octopus_Debug::getMostRelevantTraceLines(1);
            $line = array_shift($lines);

            if ($line) {
                $file = $line['nice_file'] ? $line['nice_file'] : basename($line['file']);
                $title = "{$file}, line {$line['line']}";
                $loc = $line['file'];
            }
        }
        $loc = htmlspecialchars($loc, ENT_QUOTES, 'UTF-8');
        $title = "<h1 title=\"{$loc}\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';

        $header = <<<END
<div class="octopus-debug-header">
{$title}
    <h2>
        <span class="octopus-debug-log" title="Log name">{$log}</span>
        <span class="octopus-debug-log-level" title="Log level">{$level}</span>
    </h2>
</div>
END;
        if ($this->title) {
            $title = '<h1 class="octopus-debug-title">' . htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8') . '</h1>';
        } else {
            $classes['octopus-debug-no-title'] = true;
        }

        $title .= <<<END
<h2 class="octopus-debug-subtitle">{$this->log}</h2>
END;

        $nav = array();
        $content = array();

        foreach($this->sections as $section) {
            if (empty($nav)) {
                $liClass = ' class="octopus-debug-active"';
                $contentClass = '';
            } else {
                $liClass = '';
                $contentClass = ' octopus-debug-hidden';
            }

            $id = self::getNextID();

            $nav[] = <<<END
<li{$liClass}><a href="#{$id}">{$section['name']}</a></li>
END;


            $content[] = <<<END
<div id="{$id}" class="octopus-debug-content-item{$contentClass}">
{$section['content']}
</div>
END;
        }

        $nav = implode("\n", $nav);
        $content = implode("\n", $content);

        $nav = <<<END
<ul class="octopus-debug-nav">
{$nav}
</ul>
END;

        $content = <<<END
<div class="octopus-debug-content">
{$content}
</div>
END;

        //$classes['octopus-debug-level-error'] = true;

        $classes = implode(' ', array_keys($classes));

        $html = <<<END
<div class="octopus-debug $classes">
    <div class="octopus-debug-inner">
        {$header}
        {$content}
        {$nav}
    </div>
</div>
END;

        if ($return) {
            return $html;
        } else {
            echo $html;
        }

    }

    public function __toString() {
        return $this->render(true);
    }

    /**
     * @return String A block of HTML describing the current error_reporting
     * state.
     */
    private static function getErrorReportingHtml() {

        $flags = implode(' | ', Octopus_Debug::getErrorReportingFlags());

        $display_errors = ini_get('display_errors') ? 'on' : 'off';

        $elapsed = round(microtime(true) - $_SERVER['REQUEST_TIME_MILLISECOND'], 3);

        // NOTE: the OCTOPUS_TOTAL_RENDER_TIME is replaced by render_page.

        return <<<END
        <ul class="octopusDebugErrorReporting">
        <li>error_reporting: $flags</li>
        <li>display_errors: $display_errors</li>
        <li>$elapsed <!-- OF_OCTOPUS_TOTAL_RENDER_TIME --> sec</li>
        </ul>
END;


    }

    private static function getNextID() {
        return 'octopus-debug-item-' . (self::$nextID++);
    }

}