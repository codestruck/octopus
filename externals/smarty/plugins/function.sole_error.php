<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {sole_error} function plugin
 *
 * File:       function.sole_error.php<br>
 * Type:       function<br>
 * Name:       sole_error<br>
 * Date:       29.Sep.2008<br>
 * Purpose:    Prints out a list of checkbox input types<br>
 * Input:<br>
 *           - name       (optional) - string default "checkbox"
 *           - values     (required) - array
 *           - options    (optional) - associative array
 *           - checked    (optional) - array default not set
 *           - separator  (optional) - ie <br> or &nbsp;
 *           - output     (optional) - the output next to each checkbox
 *           - assign     (optional) - assign the output as an array to this variable
 * Examples:
 * <pre>
 * {html_checkboxes values=$ids output=$names}
 * {html_checkboxes values=$ids name='box' separator='<br>' output=$names}
 * {html_checkboxes values=$ids checked=$checked separator='<br>' output=$names}
 * </pre>
 * @link http://smarty.php.net/manual/en/language.function.html.checkboxes.php {html_checkboxes}
 *      (Smarty online manual)
 * @author     Christopher Kvarme <christopher.kvarme@flashjab.com>
 * @author credits to Monte Ohrt <monte at ohrt dot com>
 * @version    1.0
 * @param array
 * @param Smarty
 * @return string
 * @uses smarty_function_escape_special_chars()
 */
function smarty_function_sole_error($params, &$smarty)
{
    $errors = null;
    $type = null;
    $desc = null;

    $extra = '';

    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'errors':
            case 'type':
            case 'desc':
                $$_key = $_val;
                break;

        }
    }

    $_html_result = array();

    if (!$errors || count($errors) < 1) {
        return '';
    }

    $class = '';
    if ($type) {
        $class = " class=\"$type\"";
    }

    $_html_result[] = "<div class=\"formErrorResponse\"><div$class>";

    if ($desc) {
        $_html_result[] = "<span>$desc</span>";
    }

    $_html_result[] = '<ul>';

    foreach ($errors as $line) {
         $_html_result[] = "<li>$line</li>";
    }

    $_html_result[] = '</ul>';
    $_html_result[] = '</div></div>';


    if(!empty($params['assign'])) {
        $smarty->assign($params['assign'], $_html_result);
    } else {
        return implode("\n",$_html_result);
    }

}

?>
