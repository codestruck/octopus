{***********************************************************
 * scaffold_index.tpl
 * View rendered by Octopus_Controller_Scaffolding for the
 * 'index' action.
 * To customize this for your site, create the file
 * site/views/scaffold_index.tpl
 * --------------------------------------------------------
 * Copyright (c) 2012 Codestruck, LLC.
 * Provided under the terms of the MIT license. See the
 * LICENSE file for more information.
 **********************************************************}

<h1>{$TITLE}</h1>

{$table}

{if $add_url}
<a class="add" href="{$add_url}">Add {$model|humanize}</a>
{/if}