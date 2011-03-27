<?php
    
    /*
     * A standard page template. 
     *
     */
     
?>
<! doctype html >
<html>
<head>
<?php $page->renderHead(); ?>
</head>
<body>

    <div id="wrap">
        
        <?php
        
            $page->render('header');
            
            $page->render('content');
            
            $page->render('footer');
        
        ?>
        
    </div>
    
</body>
</html>

