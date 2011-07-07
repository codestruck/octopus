<?php if (DEV): ?>

    <h1>View Not Found</h1>

    <p>
        There's no view available for this path
        <?php if ($resolved_path != $path): ?>
        (<strong><?php echo h($path) ?></strong>, resolved to <strong><?php echo h($resolved_path) ?></strong>).
        <?php else: ?>
        (<strong><?php echo h($path) ?></strong>).
        <?php endif ?>

    </p>

<?php if (empty($view_paths)): ?>
    <p>
        <strong>There was not enough information in the path to guess
        where the view might be stored.</strong>
    </p>
<?php else: ?>

    <p>
    Here is where we looked for views:
    </p>

    <ul class="viewPathList">
    <?php
        foreach($view_paths as $p) {

            $p = h($p);
            echo <<<END
            <li style="padding: 2px 0;">
                $p
            </li>
END;

        }
    ?>
    </ul>

<?php endif ?>

    <p>
        To change the contents of this view, edit the file
        <em>site/views/sys/view_not_found.php</em>
    </p>

<?php endif ?>
