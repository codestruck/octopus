<!doctype html>
<html>
{$HEAD}
<body class="{$ACTION_AS_CLASS} action-{$ACTION|to_css_class}">

	{render_flash()}

	<div id="view-content">
    {$view_content}
    </div>

    <div id="footer">
    	This is the default octopus theme.
    </div>

</body>
</html>

