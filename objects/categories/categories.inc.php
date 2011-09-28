<?php

/**
 * Предоставляет фукнции управления категориями в интернет-магазине
 * @uses ObjectCache
 * @uses ObjectCommon
 * @version 1.0.1
 */
class ObjectCategories extends Object implements DatabaseInterface
{
	const CacheClass = "categories";

	private $categories = false;

	static public function get_queries()
	{
		return array(
			'get'		=> "SELECT * FROM @pcategories",
			'add'		=> "INSERT INTO @pcategories SET @a",
			'update'	=> "UPDATE @pcategories SET @a WHERE id = @i",
			'delete'	=> "DELETE FROM @pcategories WHERE id IN (@l)",
		);
	}

	public function __construct(&$objects)
	{
		parent::__construct($objects);

		$this->load();
	}

	public function get($id)
	{
		return $this->categories->get($id);
	}

	public function get_node_ids($node)
	{
		return $this->categories->get_node_ids($node);
	}

	public function set_description($id, $description)
	{
		$category = $this->get($id);
		if ($category === false)
			false;

		$this->DB->update(array('description' => $description), $id);

		$category->description = $description;
		$this->save();

		return true;
	}

	public function get_by_name($name)
	{
		return $this->categories->get_by_name($name);
	}

	public function add($parent_id, $name, &$new_id)
	{
		$category = $this->categories->get($parent_id);
		if ($category === false)
			return "Категория-родитель не существует";

		if ($category->has_child($name))
			return "Категория с именем {$name} уже существует";

		$data = array('name' => $name, 'description' => "", 'parent_id' => $parent_id);

		$this->DB->add($data);

		$new_id = $this->DB->insert_id;

		$data['id'] = $new_id;

		$this->categories->add($data, $new_id, $parent_id);
		$this->save();

		return true;
	}

	public function delete($ids)
	{
		if (!is_array($ids))
			$ids = array($ids);

		$this->DB->delete($ids);

		while (list(, $id) = each($ids))
			$this->categories->remove($id);

		$this->save();
	}

	public function rename($id, $name)
	{
		if ($id == 0)
			return "Невозможно переименовать родительскую категорию";

		$category = $this->categories->get($id);
		if ($category === false)
			return "Запрашиваемая категория не существует";

		$this->DB->update(array('name' => $name), $id);

		$category->name = $name;

		$this->save();
	}

	public function get_ids_path($id)
	{
		return $this->categories->get_ids_path($id);
	}

	public function fill_children_tree($category, &$xml, $expand_ids = false)
	{
		$children = $category->get_children();
		while (list(, $child) = each($children))
		{
			$data = $child->get_data();
			$data['children'] = $child->has_child();

			$expand = false;
			if ($expand_ids === true)
				$expand = true;
			else if (is_array($expand_ids) && in_array($data['id'], $expand_ids))
				$expand = true;

			$node = $xml->addChild("category");

			$data['name'] = $this->Common->mb_wordwrap($data['name'], 20, " ", true);
			$data['name'] = nl2br($data['name']);

			$node->addAttribute("id", $data['id']);
			$node->addAttribute("name", $data['name']);
			$node->addAttribute("expand", $expand);
			$node->addAttribute("children", $data['children']);

			if ($expand === false || !$data['children'])
				continue;

			$this->fill_children_tree($child, $node, $expand_ids);
		}

		return true;
	}

	private function load()
	{
		if ($this->categories !== false)
			return;

		$this->categories = $this->Cache->get("categories", self::CacheClass);
		if ($this->categories !== false)
			return;

		$this->categories = new CategoriesTree();

		$result = $this->DB->get();

		while (($row = $result->fetch()))
			$this->categories->add($row, $row['id'], $row['parent_id']);

		$this->save();
	}

	private function save()
	{
		$this->Cache->set("categories", self::CacheClass, $this->categories);
	}
}

class CategoriesTree
{
	private $elements = array();

	public function get($id)
	{
		if (!isset($this->elements[$id]))
			return false;

		return $this->elements[$id];
	}

	public function get_by_name($name)
	{
		reset($this->elements);
		while(list(, $category) = each($this->elements))
		{
			if ($category->name == $name)
				return $category;
		}

		return false;
	}

	public function add($data, $id, $parent_id)
	{
		if (!isset($this->elements[$id]))
			$this->elements[$id] = new Category();

		$this->elements[$id]->set_data($data);

		if (!isset($this->elements[$parent_id]))
		{
			$this->elements[$parent_id] = new Category();
			$this->elements[$parent_id]->set_data(array('id' => $parent_id));
		}

		$this->elements[$parent_id]->add_child($this->elements[$id], $id);
	}

	public function remove($id)
	{
		if (!isset($this->elements[$id]))
			return;

		$parent = $this->elements[$id]->get_parent();
		if ($parent === null)
			return;

		$parent->remove_child($id);
		unset($this->elements[$id]);
	}

	public function get_node_ids($ids)
	{
		if (!is_array($ids))
			$ids = array($ids);

		$result = array();

		reset($ids);
		while (list(, $id) = each($ids))
		{
			if (!isset($this->elements[$id]))
				continue;

			$node = $this->elements[$id];

			$children = $node->get_children();

			$children_ids = array_keys($children);

			$result = array_merge($result, $children_ids);
			$result = array_merge($result, $this->get_node_ids($children_ids));
		}

		return $result;
	}

	public function get_ids_path($id)
	{
		if (!isset($this->elements[$id]))
			return false;

		$category = $this->elements[$id];

		$path = array();
		while ($category != null)
		{
			array_push($path, $category->id);
			$category = $category->get_parent();
		}

		return $path;
	}
}

class Category
{
	private $parent		= null;
	private $data		= array();
	private $children	= array();
	private $names		= array();

	public function __get($name)
	{
		if (!isset($this->data[$name]))
			return false;

		return $this->data[$name];
	}

	public function __set($name, $value)
	{
		$this->data[$name] = $value;
	}

	public function get_data()
	{
		return $this->data;
	}

	public function set_data($data)
	{
		$this->data = $data;
	}

	public function get_parent()
	{
		return $this->parent;
	}

	public function get_child($id)
	{
		if (!isset($this->children[$id]))
			return false;

		return $this->children[$id];
	}

	public function get_child_by_name($name)
	{
		$name = trim($name);
		reset($this->names);
		while (list($name2, $id) = each($this->names))
		{
			$name2 = trim($name2);
			if ($name2 != $name)
				continue;

			return $this->children[$id];
		}

		return false;
	}

	public function add_child(&$child, $id)
	{
		$this->children[$id] = &$child;
		$child->parent = &$this;

		$this->names[$child->name] = $id;
	}

	public function remove_child($id)
	{
		$child = $this->children[$id];

		unset($this->names[$child->name]);
		unset($this->children[$id]);
	}

	public function has_child($name = false)
	{
		if ($name === false)
			return !empty($this->children);

		return isset($this->names[$name]);
	}

	public function get_children()
	{
		return $this->children;
	}
}

?>