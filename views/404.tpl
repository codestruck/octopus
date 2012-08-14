{***********************************************************
 * 404.tpl
 * Default view rendered for responses with the HTTP status
 * code 404 (Not Found).
 * To customize this for your site, create the file
 * site/views/404.tpl
 * --------------------------------------------------------
 * Copyright (c) 2012 Codestruck, LLC.
 * Provided under the terms of the MIT license. See the
 * LICENSE file for more information.
 **********************************************************}
{if $view_paths}

    {* NOTE: When running in DEV, $view_paths are provided. By default, show a
             helpful list of where we looked for a view. *}

    <h1>View Not Found</h1>

    <p>
        Octopus could not find a view to render for this path
        {if $resolved_path != $path}
        (<strong>{$path|h}</strong>, resolved to <strong>{$resolved_path|h}</strong>).
        {else}
        (<strong>{$path|h}</strong>).
        {/if}
        The places Octopus looked for a view include:
    </p>

    <ul class="viewPathList">
    {foreach $view_paths as $p}
    <li style="padding: 2px 0;">{$p|h}</li>
    {/foreach}
    </ul>

    <p>
        Note: You are seeing this notice because your site is currently
        running in <strong>DEV</strong> mode. If you were running in
        <strong>LIVE</strong>, this would be
        a simple "Not Found" 404 error. To customize the appearance of
        404 errors for your site, create the file <em>site/views/404.tpl</em>
    </p>

{else}

    <h1>Not Found</h1>

    <p>
        The page you were looking for could not be found.
    </p>

{/if}