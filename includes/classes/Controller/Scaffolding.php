<?php

abstract class Octopus_Controller_Scaffolding extends Octopus_Controller {

    /**
     * Set this to any model you want to scaffold index, add, edit, view, and
     * delete actions for.
     */
    protected $scaffold = null;

    public function _default($action, $args) {
   		return array('controller_links' => $this->getControllerLinks());
    }

    /**
     * Method used for a generic "add" action.
     */
    public function addAction() {

        $this->view = 'scaffold_add';

        $model = $this->getModel();

        if (!$model) {
        	return $this->notFound();
        }

        $item = new $model();
        $form = $this->createScaffoldForm($item, 'add');

        $index_url = $this->getActionUrl('');

        set_title('Add ' . humanize($model));

        if ($form->wasSubmitted() && $form->validate()) {

            $values = $form->getValues();
            $item->setData($values);

            if ($item->save()) {
                set_flash("Added " . h($item));
                return $this->redirect($index_url);
            }

        }

        $controller_links = $this->getControllerLinks();

        return compact('model', 'form', 'index_url', 'controller_links');
    }

    /**
     * Method used for a generic "delete" action.
     */
    public function deleteAction($id) {

        $this->view = 'scaffold_delete';

        $model = $this->getModel();

        if (!$item) {
            return $this->notFound();
        }

        $item = call_user_func(array($model, 'get'), $id);

        set_title("Delete " . humanize($model));

        $index_url = $this->getActionUrl('');

        $form = new Octopus_Html_Form('delete' . $model);
        $form->addClass('scaffold-delete');
        $form->addButton('submit', 'Delete');

        if ($form->wasSubmitted() && $form->validate()) {

            $name = h($item);
            $item->delete();
            set_flash("Deleted $name");

            return $this->redirect($index_url);
        }

        // Allow, e.g. a Product model to be referred to as $product
        $var = underscore($model);
        if (!isset($$var)) $$var = $item;

        $controller_links = $this->getControllerLinks();

        return compact('item', $var, 'model', 'form', 'index_url', 'controller_links');
    }


    /**
     * Method used for a generic "edit" action.
     */
    public function editAction($id) {

        $this->view = 'scaffold_edit';

        $model = $this->getModel();

        if (!$model) {
        	return $this->notFound();
        }

        $item = call_user_func(array($model, 'get'), $id);

        if (!$item) {
            return $this->notFound();
        }

        set_title("Edit " . humanize($model));

        $index_url = $this->getActionUrl('');

        $form = $this->createScaffoldForm($item, 'edit');

        if ($form->wasSubmitted()) {

            if ($form->validate()) {

                $values = $form->getValues();
                $item->setData($values);

                if ($item->save()) {
                    set_flash('Saved changes to ' . h($item));
                    return $this->redirect($index_url);
                }

            }
        } else {
            $form->setValues($item);
        }

        // Allow, e.g. a Product model to be referred to as $product
        $var = underscore($model);
        if (!isset($$var)) $$var = $item;

        $controller_links = $this->getControllerLinks();

        return compact('id', 'model', 'item', $var, 'form', 'index_url', 'controller_links');
    }

    /**
     * Method used for a generic "index" (list) action.
     */
    public function indexAction() {

        $this->view = 'scaffold_index';

        $model = $this->getModel();

        $table = null;
        $add_url = '';

        if ($model) {

        	// NOTE: Unlike all the other actions, 'index' can still be valid
        	// if no scaffolding model is set for this class.

	        set_title(pluralize(humanize($model)));

    	    $table = $this->createScaffoldTable($model);
	        $add_url = $this->getActionUrl('add');

	    } else {

	    	set_title(guess_site_name());

	    }

        $controller_links = $this->getControllerLinks();

        return compact('model', 'table', 'add_url', 'controller_links');
    }

    /**
     * Method used for a generic "view" action.
     */
    public function viewAction($id) {

        $this->view = 'scaffold_view';

        $model = $this->getModel();

        if (!$model) {
        	return $this->notFound();
        }

        $item = call_user_func(array($model, 'get'), $id);

        if (!$item) {
            return $this->notFound();
        }

        $index_url = $this->getActionUrl('');

        $fields = array();
        foreach($item->getFields() as $f) {
            $fields[] = $f->getFieldName();
        }

        // Allow, e.g. a Product model to be referred to as $product
        $var = underscore($model);
        if (!isset($$var)) $$var = $item;

        $controller_links = $this->getControllerLinks();

        return compact('id', 'item', $var, 'model', 'fields', 'index_url', 'controller_links');

    }

    /**
     * @return Array Keys are text, values are urls. Links to the controllers
     * in the site's controllers/ directory. This is used by the default theme
     * to render a nav.
     */
    protected function getControllerLinks() {

        $links = array();

        $files = glob(get_option('SITE_DIR') . 'controllers/*.php');
        if (!$files) return $links;

        foreach($files as $f) {

            $name = basename($f, '.php');
            $url = '/' . dashed($name);
            $name = humanize($name);

            $links[$name] = u($url);

        }

        return $links;

    }

    /**
     * @return String The name of the model being scaffolded.
     */
    protected function getModel() {

    	return $this->scaffold;

    }

    private function createScaffoldForm($item, $action) {

        $form = new Octopus_Html_Form($action . get_class($item));

        $form->addClass('scaffold-' . $action);

        foreach($item->getFields() as $field) {
            $field->addToForm($form);
        }

        $form->addButton('submit', 'Save');

        $fields = $form->getFields();
        $first = array_shift($fields);
        if ($first) {
            $first->autoFocus();
        }

        return $form;

    }

    private function createScaffoldTable($model) {

        $table = new Octopus_Html_Table(camel_case($model) . 'Table');

        $table->addClass('scaffold-list');

        $item = new $model();
        foreach($item->getFields() as $field) {
            $field->addToTable($table);
        }

        $table->addColumns(array(
            'actions' => array(
                'view' => array('url' => 'view/{$id}'),
                'edit' => array('url' => 'edit/{$id}'),
                'delete' => array('url' => 'delete/{$id}')
            )
        ));

        $table->setDataSource(call_user_func(array($model, 'all')));

        return $table;

    }

}
