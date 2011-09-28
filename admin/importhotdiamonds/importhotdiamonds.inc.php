<?php

/**
 * Модуль имопорта изделий в юв.интернет магазин  из Hotdiamonds
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @uses ObjectProducts
 * @uses ObjectCategories
 * @version 1.0.1
 */
class AdminImportHotdiamonds extends ComponentAdmin
{
	const FILE_LOG = "img/log/hotdiamonds_log.txt";
	const FILE_RESULT = "img/log/hotdiamonds_result.txt";
	const FILE_XML = "img/log/hotdiamonds_data.xml";
	const DELIVERY = "hotdiahmonds";

	const MAX_ITEMS	= 500;

	private $sql = "";

	private $add_product_buf = array();

	private $categories = array();

	private $product_id_max;

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Импорт товаров Hotdiamonds");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Импорт товаров Hotdiahmonds");
	}

	public function get_access_overrides()
	{
		return array();
	}

	public function on_index()
	{
		$this->Templates->set_page("");

		if ($this->EasyForms->field("download_xml"))
			$this->download_xml();
		if ($this->EasyForms->field("download_images"))
			$this->download_images();
		if ($this->EasyForms->field("generate"))
			$this->generate();
		if ($this->EasyForms->field("delete_old"))
			$this->delete_old();
		else if ($this->EasyForms->field("remmove_new"))
			$this->move_new();
	}

	private function download_xml()
	{
		$this->Files->get_file("http://www.silverlife.ru/585_ru.xml", MAIN_LOCATION.self::FILE_XML);
	}

	private function delete_old()
	{
		$this->Products->delete_images_by_delivery(self::DELIVERY);
	}

	private function download_images()
	{
		set_time_limit(0);

		@unlink(MAIN_LOCATION.self::FILE_LOG);

		$content = file_get_contents(MAIN_LOCATION.self::FILE_XML);

		$doc = new DOMDocument();
		try
		{
			$doc->loadXML($content);
			if ($doc === false)
				throw new Exception();
		}
		catch (Exception $e)
		{
			$this->Log->error("Incorrect document");
		}

		$xpath = new DOMXpath($doc);

		$items = $xpath->query("//offer");

		$this->log("count ".$items->length);

		$dir = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_IMPORT_DIR_HOTDIAMODS;
		@mkdir($dir);

		$i = 0;
		$errors = false;

		foreach($items as $item)
		{
			$i++;
			$fields = array();

			foreach ($item->childNodes as $node)
			{
				$name = mb_strtolower(trim($node->nodeName));
				$value = trim($node->nodeValue);
				$fields[$name] = (string) $value;
			}

			$item_id = $item->getAttribute("id");

			if ($fields['picture'] == "http://www.silverlife.ru/images/smallEmptyImage.jpg")
				continue;

			if  (!isset($fields['picture']))
			{
				$this->log("not find file");
				continue;
			}

			if (!isset($fields['vendorcode']) || empty($fields['vendorcode']))
			{
				$this->log("not find vendorcode");
				continue;
			}

			$file_destionation = "$dir{$fields['vendorcode']}.jpg";

			if (file_exists($file_destionation))
				continue;

			try
			{
				$fields['picture'] = mb_ereg_replace("/s/", "/b/", $fields['picture']);
				$result = $this->Files->get_file($fields['picture'], $file_destionation);
			}
			catch (Exception $e)
			{
				$this->log("error");
				continue;
			}
			if ($result)
				$this->log("write");
			else
			{
				$this->log("error");
				continue;
			}
			$this->log($i);
		}
		$this->log("end i=$i");
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
			$this->Log->error("Incorrect document");
		}

		$xpath = new DOMXpath($doc);

		$this->categories = $this->get_nodes_values($xpath->query("//category"));

		$items = $xpath->query("//offer");

		$this->log("read file");
		$this->log("count ".$items->length);

		$dir = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_IMPORT_DIR_HOTDIAMODS;

		$dir_result = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_HOTDIAMONDS;
		$this->Files->remove_directory($dir_result, true);
		@mkdir($dir_result);

		$i = 0;

		$this->product_id_max = $this->Products->get_max_id();
		foreach($items as $item)
		{
			$i++;

			$product = array();

			foreach ($item->childNodes as $node)
			{
				$name = mb_strtolower(trim($node->nodeName));
				$value = $node->nodeValue;
				$product[$name] = $value;
			}

			if (!isset($product['vendorcode']))
			{
				$this->log("not find article i=$i");
				continue;
			}

			$product['code'] = $product['vendorcode'];
			$product['model'] = $product['typeprefix'];

			$file = "$dir{$product['code']}.jpg";

			if (!file_exists($file))
			{
				$this->log("not find file $file");
				continue;
			}
			else if(filesize($file) == 0)
			{
				$this->log("file size is 0 of $file");
				continue;
			}

			$product['picture'] = $file;
			try
			{
				$this->save_product($product);
			}
			catch(Exception $e)
			{
				$this->log("error save product". $e->getMessage(). " ".$product['code']);
			}
			$this->log($i);
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

		$category_data = $this->get_category($data);
		if ($category_data === false)
		{
			$this->log("Can't find category {$data['categoryid']} {$data['name']}");
			return false;
		}

		$this->product_id_max++;

		if (!$this->save_prop_img(	IMAGE_NAME_SMALL,
						$data,
						array(IMAGES_SMALL_WIDTH, IMAGES_SMALL_HEIGHT)))
			return false;

		if (!$this->save_prop_img(	IMAGE_NAME_BIG,
						$data,
						array(IMAGES_BIG_WIDTH, IMAGES_BIG_HEIGHT)))
			return false;

		if (!isset($data['weight']))
			$data['weight'] = 0;
		$this->format_double($data['weight']);

		$data['model'] = htmlspecialchars($data['model']);
		$data['metal_id'] = $this->Dictionaries->metals->get_id("Серебро 925");

		if (($pos = mb_strpos($data['description'], "}")))
			$data['description'] = substr($data['description'], $pos + 1);
		$str1 = "/* Style Definitions */
 table.MsoNormalTable
	{mso-style-name:\"Обычная таблица\";
	mso-tstyle-rowband-size:0;
	mso-tstyle-colband-size:0;
	mso-style-noshow:yes;
	mso-style-parent:\"\";
	mso-padding-alt:0cm 5.4pt 0cm 5.4pt;
	mso-para-margin:0cm;
	mso-para-margin-bottom:.0001pt;
	mso-pagination:widow-orphan;
	font-size:10.0pt;
	font-family:\"Times New Roman\";
	mso-ansi-language:#0400;
	mso-fareast-language:#0400;
	mso-bidi-language:#0400;}";
		if (($pos = mb_strpos($data['description'], $str1)));
			$data['description'] = mb_substr($data['description'], $pos + 1 + strlen($str1));
		if (($pos = strpos($data['description'], "Normal
  0


  false
  false
  false")))
			$data['description'] = substr($data['description'], $pos + 1);
		if (!isset($data['description']))
			$data['description'] = "";
		if (($pos = strpos($data['description'], "Купить для")))
			$data['description'] = substr($data['description'], 0, $pos);
		if (($pos = strpos($data['description'], "Так-же вы можете посмотреть:")))
			$data['description'] = substr($data['description'], 0, $pos);

		$data['description'] = trim($data['description']);

		$collection_id = 0;
		if (!empty($data['vendor']))
		{
			$collection = $this->Dictionaries->Collections->get((array($data['vendor'], self::DELIVERY)));
			if ($collection === false)
				$collection_id = $this->Dictionaries->add("collections", array('name' => $data['vendor'], 'delivery' => self::DELIVERY));
			else
			{
				$values = array_values($collection);
				$collection_id = $values[0];
			}
			echo $collection_id."  ";
		}

		$ordered_data = array(
				$this->product_id_max,
				"",			// links
				"",			// complects
				0,			// child_id
				$category_data['parent_id'],
				0,			// stone_id
				0,			// stone2_id
				0,			// stone4_id
				$data['metal_id'],
				$data['model'],
				"h".$data['code'],
				time(),			// add_time
				$data['price'] * 1.5,
                                $data['price'],
				$data['weight'],
				0,			// new
				"hotdiahmonds",
				0,			// sale
				$collection_id,		// collection
				0,			// men
				"",			// gem
				0,			// probe_id
				0,			// wedding
				$data['description'],
				1
		    );

		if (count($this->add_product_buf) == self::MAX_ITEMS)
			$this->add_product_gen();

		$values = implode("', '", $ordered_data);
		$this->add_product_buf[] =  "('".$values."')";
		return true;
	}

	private function move_new()
	{
		rename(MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_HOTDIAMONDS, MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_FILES_DIRECTORY);
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
								'name'		=> $name),
							$sizes,
							PRODUCTS_RESULT_HOTDIAMONDS);
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

	private function get_category($data)
	{
		$category_id = trim($data['categoryid']);

		if (!isset($this->categories[$category_id]))
			return false;

		$category_name = $this->categories[$category_id];
		$model = $data['model'];

		$category = false;
		if ($category_name == "Гарнитуры")
			$category = $this->Categories->get_by_name("Комплекты");
		else if (stristr($model, "Кулон") || stristr($model, "кулоны"))
			$category = $this->Categories->get_by_name("Подвески");

		else if (stristr($model, "Икон") || stristr($model, "икон") || stristr($model, "образок") || stristr($model, "Образок")
			|| stristr($model, "Ангел Хранитель"))
			$category = $this->Categories->get_by_name("Иконы");

		else if (stristr($model, "Крест") || stristr($model, "крест") || stristr($model, "распятине")
			|| stristr($model, "Распятие") || stristr($model, "Воскресение") || stristr($model, "спас")
			|| stristr($model, "Спас") ||stristr($model, "господь"))
			$category = $this->Categories->get_by_name("Кресты");

		else if (stristr($model, "Запонк") || stristr($model, "запонк"))
			$category = $this->Categories->get_by_name("Запонки");

		else if (stristr($model, "Шнурок") || stristr($model, "шнурок"))
			$category = $this->Categories->get_by_name("Шнурки");

		else if (stristr($model, "Брелок") || stristr($model, "брелок"))
			$category = $this->Categories->get_by_name("Брелоки");

		else if (stristr($model, "Зажим") || stristr($model, "зажим"))
			$category = $this->Categories->get_by_name("Зажимы");
		else
			$category = $this->Categories->get_by_name($category_name);

		if ($category === false)
			$category = $this->Categories->get_by_name($category_name);
		if ($category === false)
			$category = $this->Categories->get_by_name("Другие");

		return array('parent_id' => $category->id, 'child_id' => 0);
	}

	private function add_product_gen()
	{
		if (empty($this->add_product_buf))
			return;

		$this->sql .= "INSERT IGNORE INTO itw_products VALUES ".implode(", ", $this->add_product_buf)."; \n\n";
		$this->add_product_buf = array();
	}

	private function get_nodes_values($nodes)
	{
		$values = array();
		foreach($nodes as $node)
		{
			$id = $node->getAttribute("id");
			$values[$id] = $node->nodeValue;
		}

		return $values;
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
