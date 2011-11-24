<!doctype html>
<html>
{$HEAD}
<body class="{$ACTION_AS_CLASS} action-{$ACTION|to_css_class}">

	<div id="wrap">

		<header>

			<a class="logo" href="{"/"|u}"><h1>{$SETTINGS['site.name']|h}</h1></a>

			{if $SETTINGS['site.slogan']}
			<h2 class="slogan">{$SETTINGS['site.slogan']|h}</h2>
			{/if}

			{if $controller_links}
			<nav class="controller-links">
			{foreach from=$controller_links key=text item=url}
			<li><a href="{$url|h}">{$text|h}</a></li>
			{/foreach}
			</nav>
			{/if}

		</header>

		<section id="content">

			{render_flash()}

			<div id="view-content">
		    {$view_content}
		    </div>

	   	</section>

	   	<footer>
	    	This is the default Octopus theme. You're welcome.
	    </footer>

	</div> <!-- #wrap -->

</body>
</html>

