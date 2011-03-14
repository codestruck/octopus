<?php /*:folding=explicit:collapseFolds=1:*/

    /**
     * Outputs the arguments passed to it along w/ debugging info.
     * @param mixed Any arguments you want dumped.
     */
    function dump_r() {
        
        if ((defined('LIVE') && LIVE) || (defined('STAGING') && STAGING)) {
            // TODO: Log?
            return;
        }

        $er = error_reporting();
        
        if (($er & E_NOTICE) !== E_NOTICE) {
            // Only dump stuff when we are supposed to
            return;
        }

        if (function_exists('cancel_redirects')) {
            cancel_redirects();
        }
        
        $args = func_get_args();
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            // Probably running on command line
            foreach($args as $arg) {
                var_dump($arg);
            }
            return;
        }
        
        $styles = array(
            'wrapper' => array(
                'display' =>      'block',
                'float' =>        'none',
                'overflow' =>     'hidden',
                'margin' =>       '10px',
                'padding' =>      '10px',
                'border' =>       '4px solid #808080',
                'background' =>   '#f0f0f0',
                'color' =>        'black',
                'font-family' =>  'monospace',
                'font-size' =>    '14px'
            ),
            'table' => array(
              'padding-bottom' => '10px',
              'margin-bottom' => '10px'
            ),
            'var' => array(
              'background' => '#fff',
              'padding' => '10px'
            ),
            'arrays' => array(
                'float' => 'left'
            ),
            'array' => array(
            ),
            'arrayTh' => array(
                'font-weight' => 'bold',
                'padding-right' => '10px',
                'padding-bottom' => '4px',
                'text-align' => 'left'
            ),
            'arrayTd' => array(
            ),
            'backtrace' => array(
                'float' => 'right',
                'color' => '#444',
                'padding-left' => '10px',
            ),
            'footer' => array(
                'clear' => 'both',
                'font-size' => '0.65em',
                'padding-top' => '10px',
                'padding-left' => '10px',
                'color' => '#444',
                'text-align' => 'right'
            ),
            'type_info' => array(
                'display' => 'block',
                'font-size' => '0.95em',
                'margin-top' => '10px',
                'color' => '#444',
                'text-align' => 'center'
            )
        );
        
        // Variable(s)
        $style = _make_style_attr($styles['wrapper']);
        print "<div class=\"dump_r\" style=\"$style\">";
        
        $style = _make_style_attr($styles['table']);
        print "<table style=\"$style\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">";
        print '<tr>';
        foreach($args as $arg) {
            
            $var = stripslashes(var_export($arg, true));
            $htmlVar = htmlspecialchars($var);
            
            print '<td>';
            
            $style = _make_style_attr($styles['var']);
            print "<div style=\"$style\">";
            print $htmlVar;
            
            $style = _make_style_attr($styles['type_info']);
            print "<div style=\"$style\">";
            print '<strong style="font-weight: bold;">' . gettype($var) . '</strong> ';
            
            if (is_string($var)) {
                print 'strlen: ' . strlen($var) . ', mb_strlen: ' . mb_strlen($var);
            } else if (is_array($var)) {
                print 'count: ' . count($var);
            }
            
            print '</div>';            
            print "</div>";
            print '</td>';
        }
        print '</tr>';
        print '</table>';
        
        // built-in arrays
        $style = _make_style_attr($styles['arrays']);
        $thStyle = _make_style_attr($styles['arrayTh']);
        $tdStyle = _make_style_attr($styles['arrayTd']);
        print "<div style=\"$style\">";
        foreach(array('GET', 'POST', 'SERVER', 'SESSION') as $arname) {
           
           eval('$ar = $_' . $arname . ';');
           
           $id = "__dump_r_$arname";
           
           print "<a href=\"#$arname\" onclick=\"var c = document.getElementById('$id'); if (c.style.display == 'none') c.style.display = ''; else c.style.display = 'none'; return false;\">\$_$arname</a><br />";
           
           $style = _make_style_attr($styles['array']);
           print "<table id=\"$id\" style=\"display: none;$style\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">";
           foreach($ar as $key => $value) {
               
               $value = var_export($value, true);
               
               print '<tr>';
               print "<th style=\"$thStyle\">" . htmlspecialchars($key) . '</th>';
               print "<td style=\"$tdStyle\">" . htmlspecialchars(stripslashes($value)) . '</td>';
               print '</tr>';
           }
           print '</table>';
           
        }
        print '</div>';
        
        // Backtrace
        $backtrace = debug_backtrace();
        $style = _make_style_attr($styles['backtrace']);
        print "<div style=\"$style\">";
        print _backtrace_to_html($backtrace);
        print '</div>';
        
        // Error Reporting
        $style = _make_style_attr($styles['footer']);
        print "<div style=\"$style\">";
        print 'error_reporting: ' . implode(' | ', _get_error_reporting_flags());
        print '</div>';
        
        print '</div>';
    }

    /**
     * Calls dump_r and then exit().
     * @param mixed Any values you want displayed.
     */
    function dump_x() {
        $args = func_get_args();
        call_user_func_array('dump_r', $args);
        exit();
    }
    
    // Support Functions {{{ 
    
    function &_make_style_attr(&$styles) {
        $attr = '';
        foreach($styles as $key => $value) {
            $attr .= "$key: $value !important;";
        }
        return $attr;
    }
    
    /**
     * @return string A backtrace rendered as HTML.
     */
    function _backtrace_to_html(&$bt) {

        $html = '';
        $currentLine = '';
        $currentFile = '';
        
        $skipFunction = true;
        $first = true;
        
        foreach($bt as $b) {
            
            $function = (empty($b['function']) || $skipFunction) ? '' : $b['function'] . '() ';
            $file = empty($currentFile) ? '' : 'in ' . $currentFile;
            $line = empty($currentLine) ? '' : 'on line ' . $currentLine;
            
            $loc = trim($function . ' ' . $file . ' ' . $line);
            
            if ($loc !== '') {
                if ($first) {
                    $loc = "<strong style=\"font-weight:bold;\">$loc</strong>";
                    $first = false;
                }
                $html .= "-&nbsp;{$loc}<br />";
            }
            
            $currentFile = isset($b['file']) ? basename($b['file']) : '';
            $currentLine = isset($b['line']) ? $b['line'] : '';
            $skipFunction = false;
        }
        
        return $html;
    }
    
    /**
     * @return array The names of all enabled error reporting flags.
     */
    function &_get_error_reporting_flags() {
        
        $allExceptDeprecated = E_ALL & ~E_DEPRECATED;
        
        $er = error_reporting();
        $flags = array();
        
        if (($er & E_ALL) === E_ALL) {
            $flags[] = 'E_ALL';
        } else if ($er & $allExceptDeprecated === $allExceptDeprecated) {
            $flags[] = 'E_ALL (except E_DEPRECATED)';
        }
        
        
        if (empty($flags)) {
            foreach(array('E_NOTICE', 'E_ERROR', 'E_WARNING', 'E_PARSE', 'E_DEPRECATED') as $level) {
                $val = constant($level);
                if (($er & $val) === $val) {
                    $flags[] = $level;
                }
            }
        }

        if (($er & E_STRICT) === E_STRICT) {
            $flags[] = 'E_STRICT';
        }

        return $flags;
    }
    
    // }}}

?>
