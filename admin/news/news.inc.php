<?php

/**
 * Модуль управления новостями
 * @uses ObjectCache
 * @uses ObjectCommon
 * @uses ObjectEasyForms
 * @uses ObjectLists
 * @uses ObjectLog
 * @uses ObjectNews
 * @uses ObjectPages
 * @uses ObjectTemplates
 * @version 1.0.1
 */
class AdminNews extends ComponentAdmin
{
	const CacheClass = "modules_admin_news";
	const CacheUserClass = "modules_user_news";

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Новости");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница AJAX");
	}


	public function get_services()
	{
		return array('index' => "Новости");
	}

	public function get_access_overrides()
	{
		return array(
			'set_status'		=> "STATUS",
			'get_categories'	=> "INDEX",
			'add_category'		=> "ADD",
			'view'			=> "EDIT"
		);
	}

	/**
	 * Отображение списка новостей
	 */
	public function on_index()
	{
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
		$this->Templates->set_page("");

		$news_item = $this->Templates->news_item;
		if ($news_item === false)
			$this->Log->error("Can't find 'news_item' param");

		$news = $this->News->get(100, "", false);

		$news_list = $this->form_list_news($news, $this->Templates);

		$this->Templates->news_list = $news_list;
	}

	/**
	 * Изменение видимости новости
	 */
	public function on_set_status()
	{
		$fields = array(
			'name'		=> array(),
			'category'	=> array(),
			'display'	=> array()
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			exit;

		$fields['name'] = $this->News->get_path($fields['name'], $fields['category']);
		$fields['name'] = $this->Templates->clear_name($fields['name']);

		$data = $this->Pages->get_raw($fields['name']);
		$params = &$data['params'];
		if ($fields['display'] != "false")
			unset($params['News::Hide']);
		else
			$params['News::Hide'] =  "false";

		$this->Pages->update($data);
		exit;
	}

	/**
	 * Удалиние новости
	 */
	public function on_delete()
	{
		$fields = array('name'		=> array(),
				'category'	=> array());
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			exit;

		$this->News->delete($fields);
		exit;
	}

	/**
	 * Обзор новости для редактирования
	 */
	public function on_view()
	{
		$fields = array('name'		=> array(),
				'category'	=> array());
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			exit;

		$name = $this->News->get_path($fields['name'], $fields['category']);
		$page = $this->Pages->get_raw($name);

		$doc = new DOMDocument();
		if (!$doc->loadHTML("<html><head><meta http-equiv='Content-Type' content='text/html; charset=utf-8' /><body>".$page['content']."</body></html>"))
			$this->Log->error("Error parse news");

		$xpath = new DOMXpath($doc);

		$image = array();
		$img_node = $xpath->query("//img[contains(@class,'news_img')]");
		if ($img_node->length > 0)
		{
			$img_node = $img_node->item(0);
			$image['path'] = $img_node->getAttribute("src");

			$class_attr = $img_node->getAttribute("class");
			$image_params = explode(" ", $class_attr);
			$image_params = array_flip($image_params);
			$image = array_merge($image, $image_params);
		}

		$parse_data = array();
		$text_node = $xpath->query("//div[@class='news_text']");
		if ($text_node->length > 0)
		{
			$text_node = $text_node->item(0);
			$parse_data['text'] = $text_node->nodeValue;
		}

		$template = $this->Templates->get("");
		$params = $template->get_params(array("view_image"));
		if ($params === false)
			$this->Log->error("Can't find 'views' param");

		$fixes_params = array();
		reset($page['params']);
		while (list($key, $value) = each($page['params']))
		{
			$key = str_replace("::", "", $key);
			$fixes_params[$key] = $value;
		}

		$parse_data['name'] = $fields['name'];
		$xml = $this->XML->start_answer();
		$page_node = $xml->addChild("news");
		$this->XML->write_attributes($page_node, $fixes_params);
		$this->XML->write_attributes($page_node, $parse_data);
		if (!empty($image))
		{
			$view_image = $params['view_image'];
			$view_image->bind_params($image);
			$page_node->AddAttribute("img", (string)$view_image);
		}
		$this->XML->send_xml($xml);
	}

	/**
	 * Добавление категории
	 */
	public function on_add_category()
	{
		$category = $this->EasyForms->field("category");
		if (empty($category))
			exit;

		$this->News->add_category($category);
		exit;
	}

	/**
	 * Получение всех категорий
	 */
	public function on_get_categories()
	{
		Component::print_headers();
		$template = $this->Templates->get("");

		$category_item = $template->category_item;
		if ($category_item === false)
			$this->Log->error("Can't find 'category_item' param");

		$categories = $this->News->get_categories();
		echo $this->Lists->make($category_item, $categories);
		exit;
	}

	/**
	 * Сохранение отредактированной новости
	 */
	public function on_save()
	{
		$fields = array('name'			=> array(),
				'name_old'		=> array(),
				'category'		=> array(),
				'category_old'		=> array(),
				'title'			=> array('require' => false),
				'short_text'		=> array(),
				'text'			=> array('require' => false),

				'image_exist'		=> array('require' => false),
				'clear'			=> array('require' => false),
				'position'		=> array('require' => false),

				'use_title'		=> array('require' => false),
				'use_text'		=> array('require' => false),
				'use_image'		=> array('require' => false)
				);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		$old_name = $this->News->get_path($fields['name_old'], $fields['category_old']);
		$page = $this->Pages->get($old_name);

		$this->News->remove_image($fields['name_old'], $fields['category_old']);
		if ($fields['category'] != $fields['category_old'])
		{
			$category_dir_old = $this->News->get_category_dir($fields['category']);
			$category_dir = $this->News->get_category_dir($fields['category_old']);
			if (file_exists($category_dir_old))
				rename($category_dir_old, $category_dir);
		}

		if ($fields['name'] != $fields['name_old'])
		{
			$news_dir_old = $this->News->get_news_dir($fields['name_old'], $fields['category_old']);
			$news_dir = $this->News->get_news_dir($fields['name'], $fields['category']);
			if (file_exists($news_dir_old))
				rename($news_dir_old, $news_dir);
		}

		try
		{
			$this->News->save_image($fields);
		}
		catch(Exception $e)
		{
			$this->Errors->add(array("news", "news"), $e->getMessage());
			return;
		}

		$params = $this->parse_params($fields);
		$this->News->update($fields, $params);
		$this->reset_cache();
		Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "news", 'action' => "index"));
	}

	/**
	 * Добавлени новости
	 */
	public function on_add()
	{
		$fields = array('name'			=> array('require' => false),
				'category'		=> array('require' => false),
				'title'			=> array('require' => false),
				'short_text'		=> array('require' => false),
				'text'			=> array('require' => false),

				'position'		=> array('require' => false),
				'clear'			=> array('require' => false),

				'use_title'		=> array('require' => false),
				'use_text'		=> array('require' => false),
				'use_image'		=> array('require' => false),

				'files_names'		=> array('require' => false));
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		try
		{
			$this->News->save_image($fields);
			$this->News->save_files($fields);
		}
		catch(Exception $e)
		{
			$this->News->remove_files($fields['name'], $fields['category']);
			$this->Errors->add(array("news", "add"), $e->getMessage());
		}

		$params = $this->parse_params($fields);
		$this->News->add($fields, $params);

		$this->reset_cache();
		$this->reset_cache();
		Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "news", 'action' => "index"));
	}

	private function parse_params($fields)
	{
		$options = array();

		if (!empty($fields['use_title']))
			$options['News::Title'] = $fields['title'];

		if (empty($fields['use_text']))
			$options['News::UseOnlyShort'] = true;

		$options['News::Short'] = $fields['short_text'];
		$options['News::Category'] = $fields['category'];

		return $options;
	}

	private function form_list_news($news, $template)
	{
		$news_item = $template->news_item;
		if ($news_item === false)
			$this->Log->error("Can't find 'news_item' param");

		$news_list = "";
		while (list(, $row) = each($news))
		{
			$params = $row['params'];

			$data = $this->Common->copy_fields($row, array("name", "created", "updated", "login"));

			$pos = strrpos($row['name'], "/");
			if ($pos == FALSE)
				continue;

			$data['title'] = substr($row['name'], $pos + 1);
			$data['category'] = substr($row['name'], 0, $pos);

			if (isset($params['News::Hide']) && !empty($params['News::Hide']))
				$data['News::Hide'] = (string) $params['News::Hide'];
			else
				unset($data['News::Hide']);

			if (isset($params['News::Title']))
				$data['News::Title'] = (string) $params['News::Title'];
			else
				$data['News::Title'] = "";
			$data['News::Category'] = $params['News::Category'];

			$news_item->bind_params($data);

			$news_list .= (string) $news_item;
		}

		return $news_list;
	}

	private function reset_cache()
	{
		$this->Cache->delete("6_", self::CacheUserClass);
	}
}

?>