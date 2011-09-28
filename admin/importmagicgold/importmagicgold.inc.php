<?php

/**
 * Модуль имопорта изделий в юв.интернет магазин  из magicgold
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @uses ObjectProducts
 * @uses ObjectCategories
 * @version 1.0.1
 */
class AdminImportMagicGold extends ComponentAdmin
{
	const FILE_LOG = "img/log/magic_gold_log.txt";
	const FILE_RESULT = "img/log/magic_gold_result.txt";
	const FILE_XML = "img/log/magic_gold_data.xml";

	const MAX_ITEMS	= 500;

	private $count_product = 0;

	private $sql = "";

	private $add_product_buf = array();

	private $categories = array();
	private $metals = array();
	private $stones = array();
	private $collections = array();

	private $stones_ids = array();
	private $metals_ids = array();
	private $probes_ids = array();
	private $collections_ids = array();

	private $product_id_max;

	private $stones_ids_uses = array();

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Иморт товаров Magic Gold");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Импорт товаров Magic Gold");
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
		if ($this->EasyForms->field("clear"))
			$this->clear();
		if ($this->EasyForms->field("download_images"))
			$this->download_images();
		if ($this->EasyForms->field("statistic"))
			$this->statistic();
		if ($this->EasyForms->field("generate"))
			$this->generate();
		if ($this->EasyForms->field("delete_old"))
			$this->delete_old();
		if ($this->EasyForms->field("move_new"))
			$this->move_new();
	}

	private function download_xml()
	{
		$this->Files->get_file("http://magicgold.ru/xml/partners/warehouse.xml?key=438e737e734c68489825bbac6bd8614c", MAIN_LOCATION.self::FILE_XML);
	}

	private function delete_old()
	{
		$this->Products->delete_images_by_delivery("magic_gold");
	}

	private function clear()
	{
		$products = $this->Products->get_all();
		reset($products);
		while (list(, $product) = each($products))
		{
			$change = $this->clear_links_by_name($product['links']);
			$change = $change || $this->clear_links_by_name($product['complects']);


			if (!$change)
				continue;

			if (!empty($product['links']))
				$product['links'] = serialize($product['links']);
			if (!empty($product['complects']))
				$product['complects'] = serialize($product['complects']);
			
			$this->Products->update($product['id'], array(	'links'		=> $product['links'],
									'complects'	=> $product['complects']));
		}
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

		$dir = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_IMPORT_DIR_MAGIC;
		//$this->Files->remove_directory($dir, true);
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

			$this->log($item_id." i=$i");

			if  (!isset($fields['picture']))
			{
				$this->log("not find file");
				continue;
			}

			if (!isset($fields['article']))
				continue;

			$file_desctionation = "$dir{$fields['article']}.jpg";

			if (file_exists($file_desctionation))
			{
				if (filesize($file_desctionation) !== 0)
					continue;
			}

			try
			{
				$result = $this->Files->get_file($fields['picture'], $file_desctionation);
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
		}
		$this->log("end i=$i");
	}

	private function statistic()
	{
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

		$items = $xpath->query("//offer");

		@unlink(MAIN_LOCATION.self::FILE_LOG);

		foreach($items as $item)
		{
			$product = array();

			foreach ($item->childNodes as $node)
			{
				$name = mb_strtolower(trim($node->nodeName));
				$value = trim($node->nodeValue);

				$product[$name] = (string) $value;
			}

			$procent = $product['internet_price'] / 1.45;
			if ($procent < $product['diler_price'])
				$this->log("internet= {$product['internet_price']} diler_price={$product['diler_price']} 45=$procent");
		}
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
		$this->metals = $this->get_nodes_values($xpath->query("//type_of_gold//type"));
		$this->stones = $this->get_nodes_values($xpath->query("//stone"));
		$this->zodiacs = $this->get_nodes_values($xpath->query("//signs_of_zodiac//type"));
		$this->collections = $this->get_nodes_values($xpath->query("//supercollection"));

		$probes_nodes = $xpath->query("//offer//proba");
		foreach($probes_nodes as $probe_node)
		{
			$probe = trim($probe_node->nodeValue);
			if (isset($this->probes_ids[$probe]))
				continue;

			$id = $this->get_property_id("Probes", $probe);
			$this->probes_ids[$probe] = $id;
		}

		$items = $xpath->query("//offer");

		foreach($this->metals as $metal)
		{
			$id = $this->get_property_id("Metals", $metal);
			$this->metals_ids[$metal] = $id;
		}

		foreach($this->stones as $stone)
		{
			$id = $this->get_stone_id($stone);
			$this->stones_ids[$stone] = $id;
		}

		foreach($this->collections as $collection)
		{
			$id = $this->get_property_id("Collections", $collection);
			$this->collections_ids[$collection] = $id;
		}

		$this->log("read file");
		$this->log("count ".$items->length);

		$fields = array("categoryid",
				"article",
				"gold_type",
				"model",
				"proba",
				"zodiac",
				"for_men",
				"supercollectionid",
				"discount",
				"internet_price",
				"diler_price",
				"picture",
				"weight",
				"gem",
				"includesmain",
				"complects",
				"links"

		    );
		$fields_required = array (
				"categoryid",
				"article",
				"model",
				"internet_price",
				"diler_price",
				"picture"
			);

		$dir = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_IMPORT_DIR_MAGIC;

		$dir_result = MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_DIR_MAGIC;
		$this->Files->remove_directory($dir_result, true);
		@mkdir($dir_result);

		$i = 0;

		$this->product_id_max = $this->Products->get_max_id();
		foreach($items as $item)
		{
			$i++;

			$this->log("reading product $i");

			$product = array(	'includes'	=> array(),
						'complects'	=> array(),
						'links'		=> array());

			foreach ($item->childNodes as $node)
			{
				$name = mb_strtolower(trim($node->nodeName));
				$value = trim($node->nodeValue);

				if ($name == "complects")
					$product['complects'] = $this->get_children_values($node, $product);

				if ($name == "links")
					$product['links'] = $this->get_children_values($node, $product);

				switch ($name)
				{
					case "include":
					{
						if (isset($this->stones[$value]))
							$product['includes'][] = $this->stones[$value];
						else
							$this->log("not find include $value");
						break;
					}
					case "complects":
						break;
					case "links":
						break;
					default:
						$product[$name] = (string) $value;
				}
			}
			
			$this->log("read fields");

			if (isset($product['article']))
				$this->log($product['article']);
			else
			{
				$this->log("not find article i=$i");
				continue;
			}

			$bad = false;
			reset($fields);
			foreach ($fields as $field)
			{
				if (isset($product[$field]))
					continue;

				$this->log("not find field=$field in article = {$product['article']}");

				if (!in_array($field, $fields_required))
				{
					$product[$field] = "";
					continue;
				}

				$bad = true;
				break;
			}
			if ($bad)
				continue;

			$this->log("writing file");

			$item_id = $item->getAttribute("id");
			$file = "$dir{$product['article']}.jpg";

			try
			{
				if (!file_exists($file))
				{
					$this->log("not find file $file");
					continue;
				}
				else if(filesize($file) == 0)
				{
					$this->log("file size is 0 of $file");
					continue;;
				}
			}
			catch(Exception $e)
			{
				$this->log("exception write file ".$e->getMessage());
			}
			$product['picture'] = $file;
			try
			{
				$this->save_product($product);
			}
			catch(Exception $e)
			{
				$this->log("error save product". $e->getMessage(). " ".$product['article']);
			}
		}

		$this->add_product_gen();

		$this->log($this->sql, false);

		$this->log("end");

		$this->Dictionaries->update("stones", array('visible' => false));

		reset($this->stones_ids_uses);
		while(list($key, $value) = each($this->stones_ids_uses))
			$this->Dictionaries->update("stones", array('visible' => true), array('id' => $key));

		$this->log("end");
	}

	private function save_product($data)
	{
		$base_price = $data['internet_price'] / 1.45;
		if ($base_price < $data['diler_price'])
			return false;

		if (stristr($data['model'], "янтар") !== false)
			return false;

		$data['model'] = str_replace("'", "", $data['model']);
		$data['model'] = str_replace('"', "", $data['model']);
		$data['model'] = preg_replace("/\s{2,}/", " ", $data['model']);

		$category_data = $this->get_category($data);
		if ($category_data === false)
		{
			$this->log("Can't find category {$data['categoryid']} {$data['includesmain']}");
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

		if (!empty($data['gold_type']) && isset($this->metals[$data['gold_type']]))
			$data['gold_type'] = $this->metals[$data['gold_type']];

		$this->format_double($data['weight']);
		$this->format_double($data['internet_price']);
		$this->format_double($data['diler_price']);
		$this->format_double($data['discount']);

		$data['new'] = 0;
		if ($this->count_product == 302)
		{
			$data['new'] = 1;
			$this->count_product = 0;
		}
		$this->count_product++;

		if (!empty($data['supercollectionid']) && isset($this->collections[$data['supercollectionid']]))
		{
			$data['collection'] = $this->collections[$data['supercollectionid']];
			$data['collection'] = $this->get_property_id("Collections", $data['collection']);
			$data['collection'] = array_values($data['collection']);
			$data['collection'] = $data['collection'][0];
		}
		else
			$data['collection'] = "";

		$data['men'] = 0;
		if (!empty($data['for_men']))
			$data['men'] = 1;

		$data['price'] = round($data['internet_price']) + 10;
		$data['initial_price'] =  $base_price;

		$data['model'] = htmlspecialchars($data['model']);

		$data['stone_id'] = 0;
		$data['stone2_id'] = 0;
		$data['stone3_id'] = 0;
		if (isset($data['includes'][0]))
		{
			$stone_name = $data['includes'][0];
			$data['stone_id'] = $this->stones_ids[$stone_name];
			$id = $data['stone_id'];

			$this->stones_ids_uses[$id] = true;
		}
		if (isset($data['includes'][1]))
		{
			$stone_id = $data['includes'][1];
			$data['stone2_id'] = $this->stones_ids[$stone_id];
			$id = $data['stone_id'];
			$this->stones_ids_uses[$id] = true;
		}
		if (isset($data['includes'][2]))
		{
			$stone_id = $data['includes'][2];
			$data['stone3_id'] = $this->stones_ids[$stone_id];
			$id = $data['stone_id'];
			$this->stones_ids_uses[$id] = true;
		}

		$data['metal_id'] = 0;
		if (!empty($data['gold_type']))
			$data['metal_id'] = $this->metals_ids[$data['gold_type']];

		$data['probe_id'] = 0;
		if (!empty($data['proba']))
			$data['probe_id'] = $this->probes_ids[$data['proba']];

		if (empty($data['links']))
			$data['links'] = "";
		else
			$data['links'] = serialize($data['links']);

		if (empty($data['complects']))
			$data['complects'] = "";
		else
			$data['complects'] = serialize($data['complects']);

		$data['wedding'] = 0;
		if (strpos($data['model'], "обруч") || strpos($data['model'], "Обруч"))
			$data['wedding'] = 1;
		else if (strpos($data['model'], "свадеб") || strpos($data['model'], "Свадеб"))
			$data['wedding'] = 1;

		$ordered_data = array(
				$this->product_id_max,
				$data['links'],
				$data['complects'],
				$category_data['child_id'],
				$category_data['parent_id'],
				$data['stone_id'],
				$data['stone2_id'],
				$data['stone3_id'],
				$data['metal_id'],
				$data['model'],
				"m".$data['article'],
				time(),			// add_time
				$data['price'],
                                $data['initial_price'],
				$data['weight'],
				$data['new'],
				"magic_gold",
				0,			// sale
				$data['collection'],
				$data['men'],
				$data['gem'],
				$data['probe_id'],
				$data['wedding'],
				"",
				1
		    );

		if (count($this->add_product_buf) == self::MAX_ITEMS)
			$this->add_product_gen();

		$values = implode("', '", $ordered_data);
		$this->add_product_buf[] =  "('".$values."')";
		return true;
	}

	private function get_property_id($name_dictionary, $value)
	{
		$values = trim($value);
		if (empty($value))
			return 0;

		$id = $this->Dictionaries->$name_dictionary->get($value);
		if ($id === false)
			$id = $this->Dictionaries->add($name_dictionary, array('name' => $value));

		if (is_array($id))
		{
			while (list($key, $value) = each($id))
				$id1 = $value;
			$id = $id1;
		}

		return $id;
	}

	private function get_stone_id($value)
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
							PRODUCTS_RESULT_DIR_MAGIC);
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
		$main_include = $data['includesmain'];
		$model = $data['model'];

		if (!isset($this->categories[$category_id]))
			return false;

		$category_name = $this->categories[$category_id];

		if ($main_include != "" && isset($this->stones[$main_include]))
			$main_include = $this->stones[$main_include];

		$category = false;
		if (stristr($model, "Икон") || stristr($model, "икон") || stristr($model, "образок") || stristr($model, "Образок"))
			$category = $this->Categories->get_by_name("Иконы");

		else if (stristr($model, "Крест") || stristr($model, "крест"))
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
		{
			$category = $this->Categories->get_by_name("Другие");
			return array('parent_id' => $category->id, 'child_id' => 0);
		}

		if (stristr($main_include, "сапфир") !== false && stristr($main_include, "имитац") === false)
			$main_include = "сапфир";
		if (stristr($main_include, "бриллиант") !== false && stristr($main_include, "имитац") === false)
			$main_include = "бриллиант";
		if (stristr($main_include, "кварц") !== false && stristr($main_include, "имитац") === false)
			$main_include = "кварц";
		if (stristr($main_include, "аметист") !== false && stristr($main_include, "имитац") === false)
			$main_include = "аметист";
		if (stristr($main_include, "эмаль") !== false && stristr($main_include, "имитац") === false)
			$main_include = "эмаль";
		if ($main_include == "без вставки")
			$main_include = "без вставок";

		$child = $category->get_child_by_name($main_include);

		if ($child === false)
		{
			$main_include = "другое";
			$child = $category->get_child_by_name("другое");
		}
		
		if ($child === false)
			return array('parent_id' => $category->id, 'child_id' => 0);

		return array('parent_id' => $category->id, 'child_id' => $child->id);
	}

	private function move_new()
	{
		rename(MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_RESULT_DIR_MAGIC, MAIN_LOCATION.IMAGES_DIRECTORY.PRODUCTS_FILES_DIRECTORY);
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

	private function get_children_values($node, $product)
	{
		$result = array();
		$domNodeList = $node->childNodes;
		for($j = 0; $j < $domNodeList->length; $j++)
		{
			$node2 = $domNodeList->item($j);
			$value2 = (string) $node2->nodeValue;
			$value2 = trim($value2);
			if (empty($value2) || $value2 == "" || $product['article'] == $value2)
				continue;

			$result[] = $value2;
		}
		return $result;
	}

	private function clear_links_by_name(&$data)
	{
		if ($data == 's:0:"";')
		{
			$data = "";
			return true;
		}

		if (empty($data))
			return false;

		$data = unserialize($data);

		if (empty($data))
		{
			$data = "";
			return true;
		}

		$change = false;
		while(list($i, $code) = each($data))
		{
			$product = $this->Products->get_by_code($code);

			if ($product !== false)
				continue;

			$change = true;
			unset($data[$i]);
		}

		if (empty($data))
			$data = "";

		return $change;
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
