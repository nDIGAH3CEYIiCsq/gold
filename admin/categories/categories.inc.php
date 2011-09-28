<?php

/**
 * Модуль управления категориями товаров интернет-магазина
 * @uses ObjectCategories
 * @uses ObjectEasyForms
 * @uses ObjectProducts
 * @uses ObjectXML
 * @version 1.0.0
 */
class AdminCategories extends ComponentAdmin
{
	public function get_services()
	{
		return array();
	}

	public function get_access_overrides()
	{
		return array('set_description' => "EDIT");
	}

	public function on_children()
	{
		$fields = array(
			'id'		=> array(),
			'all'		=> array('require' => false),
			'active'	=> array('require' => false)
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			$this->XML->send_empty();

		$fields['all'] = intval($fields['all']);
		$fields['active'] = intval($fields['active']);

		$xml = $this->XML->start_answer();

		$category = $this->Categories->get($fields['id']);
		if ($category === false)
			$this->XML->send_empty();

		if ($fields['all'] === 1)
			$fields['all'] = true;
		else if (!empty($fields['active']))
			$fields['all'] = $this->Categories->get_ids_path($fields['active']);
		else
			$fields['all'] = false;

		$this->Categories->fill_children_tree($category, $xml, $fields['all']);

		$this->XML->send_xml($xml);
	}

	public function on_add()
	{
		$fields = array(
			'name'		=> array(),
			'parent_id'	=> array('require' => false)
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			$this->XML->send_error("Вы должны ввести имя новой категории");

		$fields['parent_id'] = intval($fields['parent_id']);

		$result = $this->Categories->add($fields['parent_id'], $fields['name'], $new_id);
		if ($result !== true)
			$this->XML->send_error($result);

		$xml = $this->XML->start_answer();

		$xml->addChild("id", $new_id);

		$this->XML->send_xml($xml);
	}

	public function on_rename()
	{
		$fields = array(
			'id'	=> array(),
			'name'	=> array('require' => false)
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			$this->XML->send_empty();

		$fields['name'] = trim($fields['name']);
		if (empty($fields['name']))
			$this->XML->send_error("Не указано новое имя категории");

		$category = $this->Categories->get($fields['id']);
		if ($category === false)
			$this->XML->send_error("Выбранная категория не найдена");

		$parent = $category->get_parent();
		if ($parent === false)
			$this->XML->send_error("Невозможно переименовать корневую категорию");

		$name_exist = $parent->has_child($fields['name']);
		if ($name_exist === true)
			$this->XML->send_error("Категория с указанным именем уже существует");

		$this->Categories->rename($fields['id'], $fields['name']);

		$this->XML->send_empty();
	}

	public function on_delete()
	{
		$fields = array(
			'id' => array()
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			$this->XML->send_empty();

		$categories_ids = $this->Categories->get_node_ids($fields['id']);
		$categories_ids[] = $fields['id'];

		$result = $this->Products->get_by_categories($categories_ids);
		if (!$result->is_empty())
			$this->XML->send_error("Вы не можете удалить категорию, в которой находятся продукты");

		$this->Categories->delete($categories_ids);

		$this->XML->send_empty();
	}

	public function on_set_description()
	{
		$fields = array('category_id'	=> array(),
				'description'	=> array('require' => false));
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			exit;

		$this->Categories->set_description($fields['category_id'], $fields['description']);
		exit;
	}
}

?>