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
    <td><pre><?php echo h(debug_var($value)); ?></pre></td>
</tr>
<?php endforeach ?>
</table>
