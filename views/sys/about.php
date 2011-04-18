<?php



?>
<h1>Project Octopus</h1>

<p>
    Yay! Octopus is running!
</p>

<h2>Settings</h2>
<table id="settings">
<?php foreach($settings as $key => $value): ?>
<tr>
    <th><?php echo h($key); ?></th>
    <td><pre><?php echo h($value); ?></pre></td>
</tr>
<? endforeach ?>
</table>


<h2>Options</h2>

<table id="options">
<?php foreach($options as $key => $value): ?>
<tr>
    <th><?php echo h($key); ?></th>
    <td><pre><?php echo h($value); ?></pre></td>
</tr>
<?php endforeach ?>
</table>





