<?php

/**
 * Модуль управления товарами интернет-магазина
 * @uses ObjectCategories
 * @uses ObjectCommon
 * @uses ObjectDictionaries
 * @uses ObjectEasyForms
 * @uses ObjectLists
 * @uses ObjectLog
 * @uses ObjectProducts
 * @uses ObjectTemplates
 * @uses ObjectXML
 */
class AdminProducts extends ComponentAdmin
{
	const FILE_LOG = "img/log/horos.txt";

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Каталог товаров");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Каталог товаров");
	}

	public function get_access_overrides()
	{
		return array(
                        'add'		=> "EDIT",
			'clear_log'	=> "DELETE",
			'get_children'	=> "INDEX"
		);
	}

	public function on_index()
	{
		$this->Templates->set_page("");

		$option_item = $this->Templates->option_item;
		if ($option_item === false)
			$this->Log->error("Can't find 'option_item' param");

		$root = $this->Categories->get(1);
		$parents = $root->get_children();

		$parents_list = "";
		while(list(, $parent) = each($parents))
		{
			$option_item->value = $parent->id;
			$option_item->name = $parent->name;
			$parents_list .= (String)$option_item;
		}
		$this->Templates->parents = $parents_list;

		$probes = $this->Dictionaries->Probes->get();
		$probes_list = "";
		while(list($name, $id) = each($probes))
		{
			$option_item->value = $id;
			$option_item->name = $name;
			$probes_list .= (String) $option_item;
		}
		$this->Templates->probes = $probes_list;
	
		$stones = $this->Dictionaries->Stones->get();
		$stones_list = "";
		while(list($name, $id) = each($stones))
		{
			$values = array_values(array_values($id));
			$values = array_values($values[0]);
			$option_item->value = $values[0];
			$option_item->name = $name;
			$stones_list .= (String)  $option_item;
		}
		$this->Templates->stones = $stones_list;

		$metals = $this->Dictionaries->Metals->get();
		$metals_list = "";
		while(list($name, $id) = each($metals))
		{
			$option_item->value = $id;
			$option_item->name = $name;
			$metals_list .= (String)  $option_item;
		}
		$this->Templates->metals = $metals_list;
	}

	public function on_get_children()
	{
		$fields = array(
			'parent_id'	=> array('filter' => FILTER_VALIDATE_INT)
		);
		$fields = $this->EasyForms->fields($fields);

		if ($fields === false)
			exit;

		$template = $this->Templates->get("");
		$option_item = $template->option_item;
		if ($option_item === false)
			$this->Log->error("Can't find 'option_item' param");

		$parent = $this->Categories->get($fields['parent_id']);
		$children = $parent->get_children();

		$children_list = "";
		while(list(, $child) = each($children))
		{
			$option_item->value = $child->id;
			$option_item->name = $child->name;
			$children_list .= (String)$option_item;
		}

		$xml = $this->XML->start_answer();
		$xml->addChild("data", (string) $children_list);
		$this->XML->send_xml($xml);

		$this->XML->send_xml($xml);
	}

	public function on_clear_log()
	{
		@unlink(MAIN_LOCATION.self::FILE_LOG);

		$dir_result = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_DIR_MAGIC;
		$this->Files->remove_directory($dir_result, true);
		@mkdir($dir_result);
	}

	public function on_add()
	{
		$fields = array(
			'id'			=> array('filter' => FILTER_VALIDATE_INT),
			'parent_id'		=> array('filter' => FILTER_VALIDATE_INT),
			'child_id'		=> array('filter' => FILTER_VALIDATE_INT),
			'name'			=> array(),
			'code'			=> array(),
			'price_initial'		=> array('filter' => FILTER_VALIDATE_INT),
			'precent'		=> array('filter' => FILTER_VALIDATE_INT),
			'weight'		=> array(),
			'gem'			=> array('require' => false),
			'wedding'		=> array('require' => false),
			'probe'			=> array(),
			'metal'			=> array('require' => false),
			'stone'			=> array('require' => false, 'filter' => FILTER_VALIDATE_INT),
			'stone2'		=> array('require' => false, 'filter' => FILTER_VALIDATE_INT),
			'stone3'		=> array('require' => false, 'filter' => FILTER_VALIDATE_INT),
			'delivery'		=> array(),
			'showing'		=> array(),
			'description'		=> array('require' => false),
		);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		$file = $this->Files->get_data("file", array());
		if ($file === false)
			throw new Exception("Ошибка запроса загрузки картинки");

		$result = $this->Products->copy_image(
							$file['tmp_name'],
							array(	'product_id'	=> $fields['id'],
								'name'		=> "small"),
							array(IMAGES_SMALL_WIDTH, IMAGES_SMALL_HEIGHT),
							true);

		if ($result !== true)
			return;

		$result = $this->Products->copy_image(
							$file['tmp_name'],
							array(	'product_id'	=> $fields['id'],
								'name'		=> "big"),
							array(IMAGES_BIG_WIDTH, IMAGES_BIG_HEIGHT),
							true);

		if ($result !== true)
			return;

		$fields['price'] = intval($fields['price_initial'] * ($fields['precent'] / 100 + 1));

		$data = array(
			$fields['id'],
			"",		// links
			"",		// complects
			$fields['child_id'],
			$fields['parent_id'],
			$fields['stone'],
			$fields['stone2'],
			$fields['stone3'],
			$fields['metal'],
			$fields['name'],
			$fields['code'],
			time(),		// add_time
			$fields['price'],
			$fields['price_initial'],
			$fields['weight'],
			0,		// new
			$fields['delivery'],
			0,		// sale
			0,		// collection
			0,		// men
			$fields['gem'],
			$fields['probe'],
			$fields['wedding'],
			$fields['description'],
			1		// showing
		    );

		$values = implode("', '", $data);
		$this->log("INSERT INTO itw_products VALUES ('".$values."'); \n");
	}

	public function on_delete()
	{
		$id = $this->EasyForms->field("id");
		$id = intval($id);
		if (empty($id))
			return;

		$this->Products->delete($id);
	}

	private function log($message)
	{
                $file = MAIN_LOCATION.self::FILE_LOG;

		$fn = fopen($file, "a");
		if ($fn === false)
                    $this->Log->error("Can't open file ".$file);

		fwrite($fn, $message."\n");
		fclose($fn);
	}
}

?>