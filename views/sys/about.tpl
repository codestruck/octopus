<h1>Project Octopus</h1>

<p>
    Yay! Octopus is running!
</p>

<h2>Settings</h2>
<table id="settings">
{foreach from=$settings key=key item=value}
<tr>
    <th>{$key|h}</th>
    <td><pre>{$value|debug_var|h}</pre></td>
</tr>
{/foreach}
</table>
