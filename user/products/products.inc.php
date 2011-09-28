<?php

/**
 * Отображение товаров в интернет магазине
 * @uses ObjectCategories
 * @uses ObjectEasyForms
 * @uses ObjectHandlers
 * @uses ObjectProducts
 * @uses ObjectTemplates
 * @uses ObjectDictionaries
 * @version 1.0.1
 */
class UserProducts extends ComponentUser
{
	public function on_data($data)
	{
		$option_item = $this->Templates->get("Шаблоны/Элементы/option_item");
		if ($option_item === false)
			$this->Log->error("Can't find 'option_item' params");

		$metals = $this->Dictionaries->Metals->get();
		$metals_list = "";
		while(list($key, $value) = each($metals))
		{
			$option_item->clear();
			$option_item->bind_params(array('id' => $value, 'name' => $key));
			$metals_list .= (string) $option_item;
		}
		$this->Templates->search_metals = $metals_list;

		$this->Templates->collections = $this->Products->get_collections();

		return "nopage";
	}

	public function on_collection($data)
	{
		$collection_name = $data['postfix'];

		$this->Templates->set_page("Коллекция");

		if ($collection_name == "Шампань")
			$collection_name = "Шампань&amp;Коньяк";

		$this->Templates->collection = $collection_name;
		$this->Templates->{"collection_".$collection_name} = true;
		$this->Templates->items = $this->Products->get_by_collection($collection_name);
	}

	public function on_main($data)
	{
		$this->Templates->new_items = $this->Products->get_news_short();
	}

	public function on_news($data)
	{
		$this->Templates->items = $this->Products->get_news(true);
	}

	public function on_catalog($data)
	{
		$params = $data['params'];
	
		$this->check_params($params, array("category", "default_category"));
		$root_category = $this->Categories->get($params['category']);
		if ($root_category === false)
			Component::redirect("");

		$this->Templates->set_page("Шаблоны/Изделия");

		if (trim($data['postfix']) == "")
		{
			$this->Templates->items = $this->Products->get_all_list();
			return;
		}

		$names = explode("/", $data['postfix']);
		if (count($names) == 0)
			Component::redirect("");

		$parent_category = $this->Categories->get_by_name(trim($names[0]));
		if ($parent_category === false)
			Component::redirect("");

		$parent_data = $parent_category->get_data();
		$parent_id = $parent_data['id'];

		$this->Templates->bind_params($parent_data, "Parent_category::");

		$child_id = false;

		if (count($names) > 1)
		{
			$child_name = $names[1];
			if($child_name != "все камни")
			{
				$child_category = $parent_category->get_child_by_name($child_name);
				if ($child_category === false)
					Component::redirect("");

				$this->Templates->bind_params($child_category->get_data(), "Child_category::");
				$child_id = $child_category->id;
			}
			else
				$this->Templates->bind_params(array('name' => "все камни", 'id' => 0), "Child_category::");
		}

		$this->Templates->items = $this->Products->get_data_by_categories($child_id, $parent_id);
	}

	public function on_search($data)
	{
		if (!$this->EasyForms->field("search", INPUT_GET))
			return;
		if (!$this->EasyForms->field("send", INPUT_GET) && !$this->EasyForms->field("page", INPUT_GET))
			return;

		$fields = array('price_max'	=> array('filter' => FILTER_VALIDATE_INT),
				'price_min'	=> array('filter' => FILTER_VALIDATE_INT),
				'category'	=> array('filter' => FILTER_VALIDATE_INT),
				'men'		=> array('filter' => FILTER_VALIDATE_INT),
				'complect'	=> array('filter' => FILTER_VALIDATE_INT),
				'code'		=> array(),
				'metal'		=> array('filter' => FILTER_VALIDATE_INT),
				'stone'		=> array('filter' => FILTER_VALIDATE_INT)
			);
		$fields = $this->EasyForms->fields($fields, array('require' => false, 'type' => INPUT_GET));

		$this->Templates->bind_params($fields, "Search::");

		if (!empty($fields['category']))
		{
			$category = $this->Categories->get($fields['category']);
			if ($category !== false)
				$fields['parent_id'] = $fields['category'];
		}

		$addin = $fields;
		$addin['search'] = 1;
		$addin['send'] = 1;

		$this->Templates->set_page("Шаблоны/Список изделий");

		$this->Templates->addin = $this->Common->format_params($addin);
		$this->Templates->items = $this->Products->get_search_data($fields, $addin);
	}

	public function on_item($data)
	{
		$names = explode(" ", $data['postfix']);
		if (count($names) < 2)
			Component::redirect("");

		$code = $names[count($names) - 1];
		$product = $this->Products->get_by_code($code);
		if ($product === false)
			Component::redirect("");

		$this->Templates->set_page("Шаблоны/Изделие");

		$product['image_big'] = $this->Products->get_image_big($product['id']);

		if (!empty($product['metal_id']))
		{
			$metal = $this->Dictionaries->Metals->get_by_id($product['metal_id']);
			$product['metal'] = $metal['name'];
		}

		if (!empty($product['probe_id']))
		{
			$probe = $this->Dictionaries->Probes->get_by_id($product['probe_id']);
			$product['probe'] = $probe['name'];
		}

		$stone_item = $this->Templates->stone_item;
		if ($stone_item === false)
			$this->Log->error("Can't find 'stone_item' param");
		$stones = "";
		$stones_fields = array("stone_id", "stone2_id", "stone3_id");
		foreach($stones_fields as $field)
		{
			if (empty($product['stone_id']))
				continue;

			$stone = $this->Dictionaries->Stones->get_by_id($product[$field]);
			if ($stone === false)
				continue;

			$stone_item->bind_params($stone);
			$stones .= (string) $stone_item;
		}

		$this->Templates->stones = $stones;

		$complect_item = $this->Templates->complect_item;
		if ($complect_item === false)
			$this->Log->error("Can't find 'complect_item' param");
		$product['complects'] = $this->get_products($product['complects'], $complect_item);

		$link_item = $this->Templates->link_item;
		if ($link_item === false)
			$this->Log->error("Can't find 'link_item' param");
		$product['links'] = $this->get_products($product['links'], $link_item);

		if (!empty($product['collection']))
		{
			$collection = $this->Dictionaries->Collections->get_by_id($product['collection']);
			if ($collection !== false)
				$this->Templates->bind_params($collection, "Collection::");
		}

		$this->Templates->bind_params($product);
		
		if (!$this->Products->bind_path_params($this->Templates, $product))
			Component::redirect ("/");

		$this->Basket->bind_product($this->Templates, $product['id']);

		$caregory_parent = $this->Categories->get($product['parent_id']);
		$this->Templates->{$caregory_parent->name} = true;
	}

	private function get_products($ids, $product_item)
	{
		if (empty($ids))
			return;

		$ids = unserialize($ids);
		if (empty($ids))
			return;

		$products = "";
		while (list(, $code) = each($ids))
		{
			$product_item->clear_params();

			$product = $this->Products->get_by_code($code);
			if ($product === false)
				continue;

			$product['image_small'] = $this->Products->get_image_small($product['id']);
			$product_item->bind_params($product);
			$products .= (string) $product_item;
		}
		return $products;
	}
}

?>
