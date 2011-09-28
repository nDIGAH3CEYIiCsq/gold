<?php

/**
 * Предоставляет функции для работы c продуктами магазина и их свойствами
 * @uses ObjectCache
 * @uses ObjectCategories
 * @uses ObjectEasyForms
 * @uses ObjectFilesis
 * @uses ObjectImages
 * @uses ObjectPagination
 * @uses ObjectProducts
 * @uses ObjectTemplates
 * @version 1.0.1
 */
class ObjectProducts extends Object implements DatabaseInterface
{
	const CacheClass = "products";

	static public function get_queries()
	{
		return array(
			'get_max_id'				=> "SELECT max(id) as max FROM @pproducts",

			'get_by'				=> "SELECT * FROM @pproducts @W @O @L",
			'get_count_by'				=> "SELECT count(*) as total FROM @pproducts @W",
			'update'				=> "UPDATE @pproducts SET @a WHERE id = @i",
			'add'					=> "INSERT INTO @pproducts SET @a",
			'delete_by_ids'				=> "DELETE FROM @pproducts WHERE id IN (@l)",
			'delete'				=> "DELETE FROM @pproducts @W",

			'get_price'				=> "SELECT * FROM @pproducts @W @R @O @L",
			'get_count_price'			=> "SELECT count(*) as total FROM @pproducts @W @R",

			'get_data'				=> "SELECT id, id, add_time, CONCAT(name, ' ', patronymic, ' ', lastname) as name, legal, delivery, pay, price, initial_price, delivery_price, status, address, paid FROM @porders @@WL @O @L",
			'get_data_count'			=> "SELECT count(*) as total FROM @porders @@W",

			'get_stones'				=> "SELECT * FROM @pstones WHERE visible = 1"
			);
	}

	public function __construct(&$objects)
	{
		parent::__construct($objects);
	}

	public function get_max_id()
	{
		$result = $this->DB->get_max_id();
		$row = $result->fetch();
		return $row['max'];
	}

	public function update($id, $data)
	{
		$this->DB->update($data, $id);
	}

	public function delete_images_by_delivery($delivery)
	{
		$result = $this->DB->get_by(array('delivery' => $delivery));
		while (($row = $result->fetch()))
		{
			$dir = $this->get_product_directory($row['id']);
			$this->Files->remove_directory($dir, true);
		}


		$this->DB->delete(array('delivery' => $delivery));
	}

	public function get_stones()
	{
		$cache_key = $this->Cache->make_key("stones");
		$stones = $this->Cache->get($cache_key, self::CacheClass);
		if ($stones !== false)
			return $stones;

		$result = $this->DB->get_stones();
		$rows = array();
		while(($row = $result->fetch()))
			$rows[] = $row;

		$this->Cache->set($cache_key, self::CacheClass, $rows, PRODUCTS_CACHE_TIME);
		return $rows;
	}

	public function get_collections()
	{
		$collections = $this->Dictionaries->Collections->get();

		$cache_key = $this->Cache->make_key("collections", array());
		$data = $this->Cache->get($cache_key, self::CacheClass);
		if ($data !== false)
			return $data;

		$collection_item = $this->Templates->get("Шаблоны/Элементы/collection_item");
		if ($collection_item === false)
			$this->Log->error("Can't find 'collection_item' params");

		$collections_list = "";
		while(list($name, $data) = each($collections))
		{
			$id = array_values($data);
			$id = array_values($id[0]);
			$id = $id[0];

			$result = $this->DB->get_count_by(array('collection' => $id));
			$data = $result->fetch();

			if ($data['total'] <= 1)
				continue;

			$collection_item->name = $name;
			$collections_list .= (string) $collection_item;
		}
		$this->Cache->set($cache_key, self::CacheClass, $collections_list, PRODUCTS_CACHE_TIME);
		return $collections_list;
	}

	public function get_by_collection($name)
	{
		$collection = $this->Dictionaries->Collections->get($name);
		if ($collection === false)
			return false;

		$collection_id = array_values($collection);
		$collection_id = array_values($collection_id[0]);
		$collection_id = $collection_id[0];

		$page = $this->Pagination->init();
		$cache_key = $this->Cache->make_key("products_by_coll", array($collection_id), $page);
		$format_data = $this->Cache->get($cache_key, self::CacheClass);
		if ($format_data !== false)
		{
			$this->Pagination->bind($page, $format_data);
			return $format_data['data'];
		}

		$result = $this->DB->get_count_by(array('collection' => $collection_id));
		$data =  $result->fetch();

		$this->Pagination->bind($page, $data);

		$result = $this->DB->get_by(array('collection' => $collection_id), array("price"), $page['limit']);
		$rows = array();
		while (($row = $result->fetch()))
			$rows[] = $row;

		$format_rows = $this->format_list($rows);
		$format_data = array('data' => $format_rows, 'total' => $data['total']);
		$this->Cache->set($cache_key, self::CacheClass, $format_data, PRODUCTS_CACHE_TIME);

		return $format_data['data'];
	}

	public function delete($ids)
	{
		if (!is_array($ids))
			$ids = array($ids);

		$this->DB->delete_by_ids($ids);

		while(list(, $id) = each($ids))
		{
			$dir = $this->get_product_directory($id);
			$this->Files->remove_directory($dir, true);
		}
	}

	public function get_stone_id($value)
	{
		if (empty($value))
			return false;

		$id = $this->Dictionaries->stones->get($value);
		if ($id === false)
			$id = $this->Dictionaries->add("stones", array('name' => $value));

		if (is_array($id))
		{
			$value = array_values($id);
			$value = array_values($value[0]);
			$id = $value[0];
		}
		return $id;
	}

	/**
	 * Отправляет все товары в JSON формате
	 */
	public function send_data()
	{
		$table = array(
			'fields'	=> array("id"),
			'count'		=> array(&$this->DB, "get_data_count"),
			'data'		=> array(&$this->DB, "get_data")
		);

		$this->Tables->send($table);
	}

	/**
	 * Получение товара по коду
	 * @param  String $code - код
	 * @return Array - товар
	 */
	public function get_by_code($code)
	{
		$result = $this->DB->get_by(array('code' => $code));
		if ($result->is_empty())
			return false;
		$row = $result->fetch();

		return $row;
	}

	public function get_news_short()
	{
		$cache_key = $this->Cache->make_key("news_short");

		$list = $this->Cache->get($cache_key, self::CacheClass);
		if ($list === false)
		{
			$result = $this->DB->get_by(array('new' => true), array("price"), array(PRODUCTS_NEWS_SHORT_COUNT, 0));
			$rows = array();
			while (($row = $result->fetch()))
				$rows[] = $row;

			$template = $this->Templates->get("Главная");
			$product_item = $template->get_param("product_item");
			if ($product_item === false)
				$this->Log->error("Can't find 'product_item' params");

			$list =  $this->format_list($rows, $product_item);
			$this->Cache->set($cache_key, self::CacheClass, $list, PRODUCTS_NEWS_CACHE_TIME);
		}
		return $list;
	}

	/**
	 * Получение списка новинок
	 * @param Boolean $all - флаг: все или нет
	 * @return String - список товинок изделий
	 */
	public function get_news($all = false)
	{
		$condition = array('new' => true);
		if ($all)
		{
			$page = $this->Pagination->init();

			$cache_key = $this->Cache->make_key("all_news", array(), $page);

			$rows = $this->Cache->get($cache_key, self::CacheClass);
			if ($rows === false)
			{
				$result = $this->DB->get_count_by($condition);

				$data =  $result->fetch();

				$this->Pagination->bind($page, $rows['total']);
				$result = $this->DB->get_by($condition, array("price"), $page['limit']);
				$rows = array();
				while (($row = $result->fetch()))
					$rows[] = $row;

				$rows = array('data' => $rows, 'total' => $data['total']);
				$this->Cache->set($cache_key, self::CacheClass, $rows, PRODUCTS_NEWS_CACHE_TIME);
			}
			else
				$this->Pagination->bind($page, $rows['total']);

                        return $this->format_list($rows['data']);
		}

		$rows = $this->Cache->get("news", self::CacheClass);
		if ($rows !== false)
			return $this->format_list($rows);

		$result = $this->DB->get_by($condition, array("price"), array(PRODUCTS_NEWS_SHORT_COUNT, 0));
		$rows = array();
		while (($row = $result->fetch()))
			$rows[] = $row;

		$this->Cache->set("news", self::CacheClass, $rows);
		return $this->format_list($rows);
	}

	/*
	 * Получение списка товаров по категории
	 */
	public function get_data_by_categories($child_id, $parent_id)
	{
		$page = $this->Pagination->init();

		if ($child_id !== false)
			$result = $this->DB->get_count_by(array('child_id' => $child_id));
		else if ($parent_id !== false)
			$result = $this->DB->get_count_by(array('parent_id' => $parent_id));
		else
			return false;

		$data = $result->fetch();
		if (empty($data['total']))
			return false;

		$this->Pagination->bind($page, $data);

		if ($child_id !== false)
			$result = $this->DB->get_by(array('child_id' => $child_id), array('price'), $page['limit']);
		else if ($parent_id !== false)
			$result = $this->DB->get_by(array('parent_id' => $parent_id), array('price'), $page['limit']);

		$rows = array();
		while (($row = $result->fetch()))
			$rows[] = $row;

		$format_rows = $this->format_list($rows);

		return $format_rows;
	}

	/*
	 * Возвращает все товары
	 */
	public function get_all()
	{
		$result = $this->DB->get_by();

		$rows = array();
		while (($row = $result->fetch()))
			$rows[] = $row;

		return $rows;
	}

	public function get_all_list()
	{
		$page = $this->Pagination->init();

		$result = $this->DB->get_count_by();

		$data = $result->fetch();
		if (empty($data['total']))
			return false;

		$this->Pagination->bind($page, $data);

		$result = $this->DB->get_by(array(), array('price'), $page['limit']);

		$rows = array();
		while (($row = $result->fetch()))
			$rows[] = $row;

		$format_rows = $this->format_list($rows);

		return $format_rows;
	}

	/**
	 * Возвращает общее количество изделий
	 */
	public function count()
	{
		$cache_key = $this->Cache->make_key("products_count");
		$data = $this->Cache->get($cache_key, self::CacheClass);
		if ($data !== false)
			return $data;

		$result = $this->DB->get_count_by();
		$total = $result->fetch("total");

		$this->Cache->set($cache_key, self::CacheClass, $total, PRODUCTS_CACHE_TIME);

		return $total;
	}

	/*
	 * Поиск изделий
	 */
	public function get_search_data($fields, $addin)
	{
		$page = $this->Pagination->init($addin);

		$condition = array();
		if (isset($fields['parent_id']) && !empty($fields['parent_id']))
			$condition['parent_id'] = $fields['parent_id'];
		if (isset($fields['child_id']) && !empty($fields['child_id']))
			$condition['child_id'] = $fields['child_id'];

		if (isset($fields['men']) && $fields['men'] == 1)
			$condition['men'] = 1;

		if (isset($fields['code']) && !empty($fields['code']))
			$condition['code'] = $fields['code'];

		if(isset($fields['complect']) && $fields['complect'] == "1")
			$condition['complects'] = array('operation' => "!=", 'values' => "");

		if (isset($fields['metal']) && !empty($fields['metal']))
		{
			switch($fields['metal'])
			{
				case "1000":
				{
					$metal_ids = array('values' => array("2", "3", "5", "6", "9", "10", "11", "12", "13"));
					break;
				}
				case "2000":
				{
					$metal_ids = "7";
					break;
				}
				case "all":
				{
					break;
				}
				default:
				{
					$metal = $this->Dictionaries->Metals->get_by_id($fields['metal']);
					if ($metal !== false)
						$metal_ids = $fields['metal'];
				}
			}
			$condition['metal_id'] = $metal_ids;
		}

		$condition_or = array();
		if (isset($fields['stone']) && !empty($fields['stone']))
		{
			$condition_or['stone_id'] = $fields['stone'];
			$condition_or['stone2_id'] = $fields['stone'];
			$condition_or['stone3_id'] = $fields['stone'];
		}

		if (empty($fields['price_max']))
			$fields['price_max'] = 999999;
		if (empty($fields['price_min']))
			$fields['price_min'] = 0;

		$condition['price'] = array('operation' => "between", 'values' => array($fields['price_min'], $fields['price_max']));

		$result = $this->DB->get_count_price(	$condition,
							$condition_or);
		$data =  $result->fetch();
		$this->Pagination->bind($page, $data);

		$data = $this->DB->get_price(	$condition,
						$condition_or,
						array('price'),
						$page['limit']);

		$rows = array();
		while (($row = $data->fetch()))
			$rows[] = $row;

		return $this->format_list($rows);
	}

	public function get_product_directory($product_id, $dir_import = false)
	{
		$directory = MAIN_LOCATION.IMAGES_DIRECTORY;
		if ($dir_import !== false)
			$directory .= $dir_import;
		else
			$directory .= PRODUCTS_FILES_DIRECTORY;

		$directory .= $product_id."/";

		return $directory;
	}


	public function copy_image($source, $data, $sizes, $dir_import = false)
	{
		$directory_name = $this->get_product_directory($data['product_id'], $dir_import);

		if (!file_exists($directory_name) && @mkdir($directory_name) === false)
			return "Не удалось создать директорию продукта $directory_name";

		$image_name = $directory_name.$data['name'].".jpg";

		if (!copy($source, $image_name))
			return "Не удалось скопировать файл $image_name";

		if (!$this->Images->image_copy_resampled($image_name, $image_name, $sizes[0], $sizes[1]))
		{
			@unlink($image_name);
			return "Не удалось сохранить изображение";
		}
		return true;
	}

	public function bind_path_params(&$template, $product)
	{
		$child_category = $this->Categories->get($product['child_id']);
		if ($child_category == false)
			return false;

		$parent_category = $this->Categories->get($product['parent_id']);

		$template->bind_params($child_category->get_data(), "Child_category::");
		$template->bind_params($parent_category->get_data(), "Parent_category::");
		return true;
	}

	public function get($id, $require = true)
	{
		$result = $this->DB->get_by(array('id' => $id));
		if ($result->is_empty())
			return false;
		$row = $result->fetch();

		return $row;
	}

	public function format_list($products, $template = null)
	{
		if ($template == null)
			$template = $this->element("Изделие");

		$product_list = "";
		while (list(, $row) = each($products))
		{
			$template->clear();

			$this->Basket->bind_product($template, $row['id']);
			$row['url_name'] = str_replace(" ", "+", $row['name']);
			$row['url_name'] = str_replace("/", " ", $row['url_name']);

			$row['image_small'] = $this->get_image_small($row['id']);
			$row['image_big'] = $this->get_image_big($row['id']);

			$template->bind_params($row);
			if ($row['sale'] != 0)
			{
				$template->price = round($row['price']*($row['sale']/100 + 1));
				$template->new_price = $row['price'];
			}
			if (!$this->bind_path_params($template, $row))
				continue;

			$product_list .= (string) $template;
		}

		return $product_list;
	}

	public function get_image_big($id)
	{
		return "/".IMAGES_DIRECTORY.PRODUCTS_FILES_DIRECTORY.$id."/".IMAGE_NAME_BIG.".jpg";
	}

	public function get_image_small($id)
	{
		return "/".IMAGES_DIRECTORY.PRODUCTS_FILES_DIRECTORY.$id."/".IMAGE_NAME_SMALL.".jpg";
	}

	private function element($name)
	{
		return $this->Templates->get("Шаблоны/Элементы/".$name);
	}
}

?>
