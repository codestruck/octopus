<h1>{$item}</h1>

<div class="scaffold-model">

	{foreach from=$fields item=f}

		<div class="prop">
			<span class="name">{$f|humanize|h}</span>
			<span class="value">{$item.$f}</span>
		</div>

	{/foreach}

</div>

<a class="back" href="{$index_url}">&laquo; Back to {$model|humanize|pluralize|h}</a>