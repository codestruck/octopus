<?php

function to_unique_slug($model) {
    $str = $model->getDisplayValue();
    return to_slug($str);
}

class SG_Model_Field_Slug extends SG_Model_Field {



}

?>
