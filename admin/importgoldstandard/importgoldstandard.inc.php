<?php

/**
 * Модуль имопорта изделий в юв.интернет магазин изделий Золотого стандарта
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @uses ObjectProducts
 * @uses ObjectProperties
 * @uses ObjectCategories
 * @version 1.0.1
 */

require_once 'excel_reader.php';
class AdminImportGoldStandard extends ComponentAdmin
{
	const FILE_DATA = "img/import/goldStandard/data.xls";
	const FILE_LOG = "img/log/gold_standard_log.txt";
	const FILE_RESULT = "img/log/gold_standard_result.txt";

        const BEGIN_ROW = 5;
	const MAX_ITEMS	= 500;

	private $product_id_max = 1;

	private $categories = array(
                        'Браслет'		=> "Браслеты",
                        'Брошь'			=> "Брошь",
                        'Булавка'		=> "Другие",
                        'Зажим'			=> "Зажимы",
                        'Запонки'		=> "Запонки",
                        'Значок'		=> "Другие",
                        'Колье'			=> "Колье",
                        'Кольцо'		=> "Кольца",
                        'лазерная гравировка'	=> "Другие",
                        'Ложка'			=> "Другие",
                        'Мешочки'		=> "Другие",
                        'Пирсинг'		=> "Пирсинг",
                        'Подвеска'		=> "Подвески",
                        'Серьги'		=> "Серьги",
                        'Сувенир'		=> "Другие",
                        'Цепь'			=> "Цепи",
			'Брелок'		=> "Другое",
			'Визитка'		=> "Другое",
			'Медаль'		=> "Другое",
			'Орден-медаль'		=> "Другое",
			'Статуэтка'		=> "Другое",
	    );

	private $categories_child = array(
			'Серьги'		=> "другое",
			'Подвески'		=> "другое",
			'Цепи'			=> "",
			'Браслеты'		=> "другое",
			'Колье'			=> "другое",
			'Брошь'			=> "другое",
			'Зажимы'		=> "",
			'Запонки'		=> "",
			'Пирсинг'		=> "",
			'Другие'		=> "",
			'Кольца'		=> "другое"
	);

	private $metals = array(
				'585'		=> "Желтое золото 585",
				'925'		=> "Серебро 925",
				'375'		=> "Золото 375",
				'777'		=> "Золото 777",
				'750'		=> "Золото 750",
				'Без пробы'	=> ""
		);

	private $sql = "";
	private $add_product_buf = array();

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Импорт товаров Gold Standard");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Импорт товаров Gold Standard");
	}

	public function get_access_overrides()
	{
		return array( );
	}

	public function on_index()
	{
		$this->Templates->set_page("");
		if ($this->EasyForms->field("generate"))
			$this->generate();
		else if($this->EasyForms->field("delete_old"))
			$this->delete_old();
		else if ($this->EasyForms->field("remmove_new"))
			$this->move_new();
	}

	private function generate()
	{
		set_time_limit(0);

		@unlink(MAIN_LOCATION.self::FILE_LOG);
		@unlink(MAIN_LOCATION.self::FILE_RESULT);

		$file_data =  MAIN_LOCATION.self::FILE_DATA;
		$data = new Spreadsheet_Excel_Reader($file_data);
		$data->setOutputEncoding("utf-8");
		$data->read($file_data);
		if ($data === false)
			$this->log("Error read file");

		$category = false;
		$dir = MAIN_LOCATION."img/import/goldStandardImages/";
		$dir_result = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_DIR_GOLD_STANDARD;
		$this->Files->remove_directory($dir_result, true);
		@mkdir($dir_result);

		$this->product_id_max = $this->Products->get_max_id();

		for ($i = self::BEGIN_ROW; $i <= $data->sheets[0]['numRows']; $i++)
		{
			$item =  $data->sheets[0]['cells'][$i];

			if (empty($item[2]))
				continue;

			$item[3] = trim($item[3]);

			if (empty($item[3]))
			{
				$category = false;

				$name = trim($item[2]);
				$category_name = trim($item[2]);
				if (!isset($this->categories[$category_name]))
				{
					$this->log("Can't find category = $category_name");
					$category_name = false;
					continue;
				}
				$category_name = $this->categories[$category_name];
				$category = $this->Categories->get_by_name($category_name);
				if ($category === false)
				{
					echo "not find category2=$category_name";
					$this->log("Cant't find category $category_name");
					continue;
				}

				continue;
			}

			if ($category === false)
			{
				echo " not find ". $item[2]. " ". $item[3]. "\n";
				continue;

			}

			$metal_name = trim($item[4]);
			$metal_name = str_replace(".", "", $metal_name);
			if (!isset($this->metals[$metal_name]))
			{
				echo "not find metall $metal_name";
				$this->log("Can't find metal $metal_name");
				continue;
			}

			$metal_name = $this->metals[$metal_name];
			$metal = $this->Dictionaries->Metals->get($metal_name);
			if ($metal === false)
			{
				echo "not find metall $metal_name";
				$this->log("Cant't find metall $metal_name");
				continue;
			}
			$metal_id = $this->Dictionaries->Metals->get_id($metal_name);

			if ($item[3] != "53164")
				continue;
			echo "weight".$item[6];
			$weight = trim($item[6]);
			$weight = str_replace(",", ".", $weight);
			$weight = floatval($weight);
			echo "  $weight";
			return;

			if (empty ($weight))
			{
				$this->log("empty weight " . $item[6] . " of code = ". $item[3]);
			}

			$price = trim($item[7]);
			$price = str_replace(",", ".", $price);
			$price = intval($price) + 1;


			if (!isset($this->categories_child[$category->name]))
			{
				$this->log("Can't find child_category for ".$category->name);
				continue;
			}
			$category_child_name = $this->categories_child[$category->name];
			if ($category_child_name == "")
				$child_id = 0;
			else
			{
				$category_child = $category->get_child_by_name($category_child_name);
				if ($category_child === false)
				{
					$this->log("Can't find 2 child_category for ".$category->name);
					continue;
				}
				$child_id = $category_child->id;
			}

			$product = array(
					'code'		=> trim($item[3]),
					'name'		=> $name,
					'metal_id'	=> $metal_id,
					'weight'	=> $weight,
					'initial_price'	=> $price,
					'category_id'	=> $category->id,
					'child_id'	=> $child_id
			);

			$file = "$dir{$product['code']}.jpg";

			if (!file_exists($file))
			{
				$this->log("not find file $file");
				continue;
			}

			$product['picture'] = $file;

			$this->save_product($product);
		}

		$this->add_product_gen();

                $this->Log($this->sql, false);
	}

	private function delete_old()
	{
		set_time_limit(0);

		$this->Products->delete_images_by_delivery("gold_standard");
	}

	private function save_product($data)
	{
		$data['name'] = str_replace("'", "", $data['name']);
		$data['name'] = str_replace('"', "", $data['name']);
		$data['name'] = preg_replace("/\s{2,}/", " ", $data['name']);

		$this->product_id_max++;

		if (!$this->save_prop_img(	IMAGE_NAME_SMALL,
						$data,
						array(IMAGES_SMALL_WIDTH, IMAGES_SMALL_HEIGHT)))
			return false;

		if (!$this->save_prop_img(	IMAGE_NAME_BIG,
						$data,
						array(IMAGES_BIG_WIDTH, IMAGES_BIG_HEIGHT)))
			return false;

		$data['price'] = intval($data['initial_price'] * 1.45);

		$ordered_data = array(
				$this->product_id_max,
				"",			// links
				"",			// complects
				$data['child_id'],	// child_id category
				$data['category_id'],
				"",			// stone_id
				"",			// stone2_id
				"",			// stone3_id
				$data['metal_id'],	// metal_id
				$data['name'],
				"g".$data['code'],
				time(),			// add_time
				$data['price'],
                                $data['initial_price'],
				$data['weight'],
				0,			// new
				"gold_standard",	// delivery
				0,			// sale
				0,			// collection
				0,			// men
				"",			// gem
				0,			// probe
				0,			// wedding
				"",			// description
				"",			// brand
				1			// showing
		    );

		if (count($this->add_product_buf) == self::MAX_ITEMS)
			$this->add_product_gen();

		$values = implode("', '", $ordered_data);
		$this->add_product_buf[] =  "('".$values."')";
		return true;
	}

	private function save_prop_img($name, $data,  $sizes)
	{
		try
		{
			$result = $this->Products->copy_image(
							$data['picture'],
							array(	'product_id'	=> $this->product_id_max,
								'name'		=> $name),
							$sizes,
							PRODUCTS_RESULT_DIR_GOLD_STANDARD);
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

	private function move_new()
	{
		rename(MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_DIR_GOLD_STANDARD, MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_FILES_DIRECTORY);
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
