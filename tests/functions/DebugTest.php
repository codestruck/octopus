<?php

class DebugTest extends Octopus_Html_TestCase {

    function testDumpPlainTextVariable() {

        $d = new Octopus_Debug();

        $d->addVariable('bar', 'foo');
        $this->assertEquals(
            <<<END
********************************************************************************
* foo (string) | "bar" - 3 chars                                               *
********************************************************************************
END
            ,
            $d->renderText(true)
        );
    }

    function testDumpPlainTextContent() {

        $d = new Octopus_Debug();
        $d->add('My Content', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');

        $this->assertEquals(
            <<<END
********************************************************************************
* My Content | Lorem ipsum dolor sit amet, consectetur adipiscing elit.        *
********************************************************************************
END
            ,
            $d->renderText(true)
        );

    }

    function testDumpPlainTextWrappingContent() {

        $d = new Octopus_Debug();
        $d->add('My Content', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit.');

        $this->assertEquals(
            <<<END
********************************************************************************
* My Content | Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem  *
*            | ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum  *
*            | dolor sit amet, consectetur adipiscing elit.                    *
********************************************************************************
END
            ,
            $d->renderText(true)
        );

    }

    function testDumpPlainTextCombinedVariableAndContent() {

        $d = new Octopus_Debug();
        $d->addVariable('bar', 'foo');
        $d->add('My Content', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');

        $this->assertEquals(
            <<<END
********************************************************************************
* foo (string) | "bar" - 3 chars                                               *
********************************************************************************
* My Content   | Lorem ipsum dolor sit amet, consectetur adipiscing elit.      *
********************************************************************************
END
            ,
            $d->renderText(true)
        );

    }

    function testDumpPlainTextSquashedRedirect() {

        $d = new Octopus_Debug();
        $d->addSquashedRedirect('/some/path/in/the/app');

        $this->assertEquals(
            <<<END
********************************************************************************
* Suppressed Redirect | /some/path/in/the/app                                  *
********************************************************************************
END
            ,
            $d->renderText(true)
        );

    }

    function testDumpVariableToHtml() {

        $d = new Octopus_Debug();
        $d->addVariable('bar', 'foo');

        $text = $d->renderText(true);

        $css = Octopus_Debug::$css;
        $js = Octopus_Debug::$js;

        $expected = <<<END
<!--

BEGIN dump_r Output {{{

Here is a plain-text version of what's below in case you need it:

$text
-->
$css
$js
<div id="__octopus_debug" class="octopusDebug">
    <ul class="octopusDebugTabButtons">
        <li id="octopusDebug2" class="octopusDebugTabButton octopusDebugTabButtonSelected">
            <a href="#" onclick="__octopus_openTab('octopusDebug3', 'octopusDebug2'); return false;">foo</a>
        </li>
    </ul>
    <div class="octopusDebugTabs">
        <div id="octopusDebug3" class="octopusDebugTab octopusDebugFirst octopusDebugLast">
            <div id="octopusDebug4" class="octopusDebugNiceOutput">
                <span class="octopusDebugString"> &quot;bar&quot;<span class="octopusDebugStringLength">&nbsp;&mdash;&nbsp;3 chars</span></span>
            </div>
        </div>
    </div>
</div>

<!-- END dump_r Output }}} -->
END;

        $this->assertHtmlEquals(
            $expected,
            $d->renderHtml(true)
        );

    }

}

?>
