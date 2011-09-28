<?php

/**
 * Отображение категорий товаров
 * @uses ObjectCategories
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @version 1.0.1
 */
class UserCategories extends ComponentUser
{
	const CacheClass = "modules_user_news";

	private $simple_categories = array(
	    "Другие" , "Сувениры", "Булавки", "Шнурки", "Запонки", "Зажимы", "Брелоки", "Цепи", "Иконы", "Пирсинг", "Часы", "Кресты", "Талисманы", "Комплекты");

	/**
	 * Биндинг категорий в левом меню и в поиске изделий
	 * @param Array $data - внутренняя информация о запросе
	 */
	public function on_menu($data)
	{
		$categories_data = $this->Cache->get("menu", self::CacheClass);
		if ($categories_data === false)
		{
			$params = $data['params'];
			$this->check_params($params, array("category"));
			$root = $this->Categories->get($params['category']);
			if ($root === false)
				return "nopage";

			$template = $this->Templates->get("Шаблоны/Меню");

			$category_params = $template->get_params(array("parent_category", "child_category", "children_categories"));
			if ($category_params === false)
				$this->Log->error("Can't find 'categories' params");

			$parent_list = "";

			$parent_param = $category_params['parent_category'];
			$child_param = $category_params['child_category'];
			$children_param = $category_params['children_categories'];

			$categories = $root->get_children();

			$categories_ordered = array();
			reset($categories);
			while (list(, $category) = each($categories))
			{
				$data_category = $category->get_data();
				$categories_ordered[$data_category['order']] = $category;
			}
			ksort($categories_ordered);
			while (list(, $parent) = each($categories_ordered))
			{
				$category_data = $parent->get_data();

				$parent_param->clear();
				if (in_array($category_data['name'], $this->simple_categories))
					$parent_param->simple = true;

				$parent_param->bind_params($category_data);

				$children = $parent->get_children();

				$child_param->bind_params(array('name' => "все камни", 'id' => 0), "Child::");
				$child_param->all_metall = true;
				$child_param->bind_params($parent->get_data(), "Parent::");
				$child_list = (string) $child_param;

				reset($children);
				while (list(, $child) = each($children))
				{
					$child_param->clear();
					$child_param->bind_params($parent->get_data(), "Parent::");
					$child_param->bind_params($child->get_data(), "Child::");

					$child_list .= (string) $child_param;
				}

				$children_param->items = $child_list;
				$parent_param->children = $children_param;

				$parent_list .= (string) $parent_param;
			}
			$categories_data = array('roots' => $parent_list);
			$this->Cache->set("menu", self::CacheClass, $categories_data);
		}

		$this->Templates->set_param("Categories::roots", $categories_data['roots']);

		$this->search($data);

		return "nopage";
	}

	/**
	 * Биндинг категорий в список для поиска
	 * @param Array $data внутрення иформ. о запросе
	 */
	public function search($data)
	{
	    $search_categories_list = $this->Cache->get("search", self::CacheClass);

	    if ($search_categories_list === false)
	    {
		$params = $data['params'];

		$this->check_params($params, array("category"));
		$category = $this->Categories->get($params['category']);
		if ($category === false)
		    return "nopage";

		$option_item = $this->Templates->get("Шаблоны/Элементы/option_item");
		if ($option_item === false)
				$this->Log->error("Can't find 'option_item' params");

		$search_categories_list = "";
		$categories = $category->get_children();
		reset($categories);
		while (list(, $parent) = each($categories))
		{
		    $data = $parent->get_data();
		    $option_item->bind_params($data);

		    $search_categories_list .= (string) $option_item;
		}
		
		$this->Cache->set("search", self::CacheClass, $search_categories_list);
	    }

	    $this->Templates->set_param("Categories::search", $search_categories_list);
	}
}

?>
