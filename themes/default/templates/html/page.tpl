<!doctype html>
<html>
{$HEAD}
<body class="{$ACTION_AS_CLASS} action-{$ACTION|to_css_class}">

        <div class="navbar">
            <div class="navbar-inner">
                <div class="container">

                    <a class="brand" href="{"/"|u}">{$SETTINGS['site.name']|h}</a>


                    {if $controller_links}
                    <ul class="nav controller-links">
                    {foreach from=$controller_links key=text item=url}
                    <li><a href="{$url|h}">{$text|h}</a></li>
                    {/foreach}
                    </ul>
                    {/if}

                </div>
            </div>
        </div>

        <div class="container">

            {render_flash()}

            <div id="view-content">
            {$view_content}
            </div>

        </div>

	   	<footer>
	    </footer>

    </div>

</body>
</html>

