<?php

    Octopus::loadClass('Octopus_Html_Form');

    $form = new Octopus_Html_Form('settings', array('method' => 'post', 'action' => 'settings'));

    $data = array();
    foreach($settings as $key => $value) {
        $field = $settings->createEditor($key);
        $form->add($field);
        $data[$key] = $value;
    }

    $form->setData($data);

?>

<h1>Settings</h1>

<?php echo $form ?>
