<?php

/**
 * Модуль имопорта изделий в юв.интернет магазин
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @uses ObjectProducts
 * @uses ObjectCategories
 * @version 1.0.1
 */
class AdminImportAdamas extends ComponentAdmin
{
	const FILE_LOG = "img/log/adamas_log.txt";
	const FILE_RESULT = "img/log/adamas_result.txt";
	const FILE_XML = "img/import/adamas_data.xml";

	const MAX_ITEMS	= 500;

	private $sql		= "";

	private $add_product_buf = array();

	private $product_id_max;

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Импорт товаров Адамас");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Импорт товаров Адамас");
	}

	public function get_access_overrides()
	{
		return array();
	}

	public function on_index()
	{
		$this->Templates->set_page("");

		if ($this->EasyForms->field("generate"))
			$this->generate();
		else if ($this->EasyForms->field("delete_old"))
			$this->delete_old();
		else if ($this->EasyForms->field("remmove_new"))
			$this->move_new();
	}

	private function generate()
	{
		set_time_limit(0);

		@unlink(MAIN_LOCATION.self::FILE_LOG);
		@unlink(MAIN_LOCATION.self::FILE_RESULT);

		$content = file_get_contents(MAIN_LOCATION.self::FILE_XML);
		$doc = new DOMDocument();
		try
		{
			$doc->loadXML($content);
			if ($doc === false)
				throw new Exceptin();
		}
		catch (Exception $e)
		{
			$this->log("Incorrect source file");
		}

		$xpath = new DOMXpath($doc);
		$items = $xpath->query("//offer");

		$dir = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_IMPORT_DIR_ADAMAS;

		$dir_result = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_DIR_ADAMAS;
		$this->Files->remove_directory($dir_result, true);
		@mkdir($dir_result);

		$category = $this->Categories->get_by_name("Серьги");
		if ($category === false)
		{
			$this->log("Can't find category Серьги");
			return;
		}
		$category_no_zipper = $category->get_child_by_name("продёвки");
		if ($category_no_zipper === false)
		{
			$this->log("Can't find category продёвки");
			return;
		}
		$category_default = $category->get_child_by_name("другое");
		if ($category_no_zipper === false)
		{
			$this->log("Can't find category другое");
			return;
		}

		$metal_id = $this->Dictionaries->Metals->get_id("Желтое золото 585");
		if ($metal_id === false)
		{
			$this->log("Can't find metal Желтое золото 585");
			return;
		}

		$this->product_id_max = $this->Products->get_max_id();

		foreach($items as $item)
		{
			$product = array();
			foreach ($item->childNodes as $node)
			{
				$name = mb_strtolower(trim($node->nodeName));
				$value = trim($node->nodeValue);

				$product[$name] = (string) $value;
			}

			if (!isset($product['article']))
			{
				$this->log("not set article");
				continue;
			}

			$product['code'] = $product['article'];
			$code = $product['code'];
			$file = "$dir$code.jpg";

			if (!file_exists($file))
			{
				$this->log("not find file $file");
				continue;
			}

			$product['parent_id'] = $category->id;
			if (strpos($product['model'], "продёвки") || strpos($product['model'], "продевки"))
				$product['child_id'] = $category_no_zipper->id;
			else
				$product['child_id'] = $category_default->id;

			$product['metal_id'] = $metal_id;
			$product['picture'] = $file;

			$this->save_product($product);
		}

		$this->add_product_gen();

		$this->log($this->sql, false);

		$this->log("end");
	}

	private function save_product($data)
	{
		$data['model'] = str_replace("'", "", $data['model']);
		$data['model'] = str_replace('"', "", $data['model']);
		$data['model'] = preg_replace("/\s{2,}/", " ", $data['model']);

		$this->product_id_max++;

		if (!$this->save_prop_img(	IMAGE_NAME_SMALL,
						$data,
						array(IMAGES_SMALL_WIDTH, IMAGES_SMALL_HEIGHT)))
			return false;

		if (!$this->save_prop_img(	IMAGE_NAME_BIG,
						$data,
						array(IMAGES_BIG_WIDTH, IMAGES_BIG_HEIGHT)))
			return false;

		$this->format_double($data['weight']);
		$this->format_double($data['price']);

		$data['price'] = intval($data['price']);
		$data['model'] = htmlspecialchars($data['model']);
		$data['initial_price'] = intval($data['price'] / 2);
		if (!isset($data['description']))
			$data['description'] = "";

		$ordered_data = array(
				$this->product_id_max,
				"",				// links
				"",				// complects
				$data['child_id'],
				$data['parent_id'],
				0,				// stone_id
				0,				// stone2_id
				0,				// stone3_id
				$data['metal_id'],
				$data['model'],
				"a".$data['code'],
				time(),				// add_time
				$data['price'],
                                $data['initial_price'],
				$data['weight'],
				0,				// new
				"adamas",
				0,				// sale
				0,				// collection
				0,				// men
				"",				// gem
				0,				// probe
				0,				// wedding
				$data['description'],		// description
				"brand",			// brand
				1				// showing
		    );

		if (count($this->add_product_buf) == self::MAX_ITEMS)
			$this->add_product_gen();

		$values = implode("', '", $ordered_data);
		$this->add_product_buf[] =  "('".$values."')";
		return true;
	}

	private function move_new()
	{
		rename(MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_DIR_ADAMAS, MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_FILES_DIRECTORY);
	}

	private function format_double(&$value)
	{
		$value = str_replace(",", ".", $value);
		$value = preg_replace("/\s+/", "", $value);
	}

	private function save_prop_img($name, $data,  $sizes)
	{
		try
		{
			$result = $this->Products->copy_image(
							$data['picture'],
							array(	'product_id'	=> $this->product_id_max,
								'name'		=> $name
							    ),
							$sizes,
							PRODUCTS_RESULT_DIR_ADAMAS);
		}
		catch(Exception $e)
		{
			$this->log("exception ".$e);
		}
		if ($result !== true)
		{
			$this->log("not copy file $result");
			return false;
		}

		return true;
	}

	private function add_product_gen()
	{
		if (empty($this->add_product_buf))
			return;

		$this->sql .= "INSERT IGNORE INTO itw_products VALUES ".implode(", ", $this->add_product_buf)."; \n\n";
		$this->add_product_buf = array();
	}

	private function log($message, $log = true)
	{
                if ($log)
                    $file = MAIN_LOCATION.self::FILE_LOG;
                else
                    $file = MAIN_LOCATION.self::FILE_RESULT;

		$fn = fopen($file, "a");
		if ($fn === false)
                    $this->Log->error("Can't open file ".$file);

		fwrite($fn, $message."\n");
		fclose($fn);
	}
}

?>
