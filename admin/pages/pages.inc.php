<?php

/**
 * Модуль управления страницами
 *
 * @uses ComponentAdmin
 * @uses ObjectAdmin
 * @uses ObjectCommon
 * @uses ObjectCompress
 * @uses ObjectDate
 * @uses ObjectEasyForms
 * @uses ObjectErrors
 * @uses ObjectLog
 * @uses ObjectPages
 * @uses ObjectPagesVersions
 * @uses ObjectTemplates
 * @uses ObjectXML
 *
 * @version 1.0.2
 */
class AdminPages extends ComponentAdmin
{
	private $version_actions = array('create' => "С", 'delete' => "У", 'edit' => "М", 'move' => "П", 'restore' => "В");

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Страницы");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница AJAX");
	}

	public function get_services()
	{
		return array('index' => "Страницы");
	}

	public function get_access_overrides()
	{
		return array(
			'main'		=> "INDEX",
			'get_menu'	=> "INDEX",
			'get_state'	=> "INDEX",

			'add'		=> "INDEX",
			'add_submit'	=> "ADD",

			'delete'	=> "INDEX",
			'delete_submit'	=> "DELETE",

			'export'	=> "INDEX",
			'export_submit'	=> "EXPORT",

			'import'	=> "INDEX",
			'import_submit'	=> "IMPORT",

			'edit'		=> "VIEW",
			'edit_submit'	=> "EDIT",

			'rename'	=> "INDEX",
			'rename_submit'	=> "EDIT",
		);
	}

	/**
	 * Отображает титульную страницу модуля
	 */
	public function on_index()
	{
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
		$this->Templates->set_page("");
	}

	/**
	 * Отображает текст титульной страницы модуля
	 */
	public function on_main()
	{
		$this->Templates->set_page("Титульная страница");
	}

	/**
	 * Восстанавливает выбранную версию страницы
	 */
	public function on_restore()
	{
		$id = $this->EasyForms->field("id");
		if ($id === false)
		{
			$this->on_index();
			return;
		}

		$row = $this->PagesVersions->get($id);
		if ($row === false)
		{
			$this->on_index();
			return;
		}

		$fields = array();
		$fields['user_id']	= $_SESSION['id'];
		$fields['name']		= $row['name'];
		$fields['data']		= $row['id'];

		$row['created'] = $row['time'];
		$row['updated'] = $row['time'];

		if (!empty($row['params']))
			$row['params'] = unserialize($row['params']);
		else
			$row['params'] = array();

		unset($row['id']);
		unset($row['time']);
		unset($row['action']);
		unset($row['data']);

		$this->Pages->replace($row);

		$fields['action']	= "restore";
		$fields['time']		= time();

		$this->PagesVersions->add($fields);

		$this->Pages->update_names(array($fields['name']), array());

		$this->on_edit($fields['name']);
		$this->update_menu(array($fields['name']), array());
	}

	/**
	 * Отображает страницу конкретной версии
	 */
	public function on_version()
	{
		$id = $this->EasyForms->field("id", INPUT_POST);
		if ($id !== false)
			$this->Templates->set_page("Версия");
		else
		{
			$id = $this->EasyForms->field("id", INPUT_GET);
			if ($id === false)
				$this->Log->error("No version id specified");

			$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
			$this->Templates->set_page("Версия/Окно");
			$this->Templates->windowed = true;
		}

		$row = $this->PagesVersions->get($id);
		if ($row === false)
			$this->Log->error("Version {$id} not found exist");

		$row['name']	= addslashes($row['name']);
		$row['content']	= htmlspecialchars($row['content']);

		$this->Templates->name = $row['name'];
		$this->Templates->time = $row['time'];
		$this->Templates->content = $row['content'];

		if (empty($row['params']))
			return;

		$params = unserialize($row['params']);

		$param_names = array();
		$param_contents = array();

		while (list($name, $value) = each($params))
		{
			$name = addslashes($name);

			$value = addslashes($value);
			$value = str_replace("\r", "", $value);
			$value = str_replace("\n", "\\n", $value);

			array_push($param_names, $name);
			array_push($param_contents, $value);
		}

		$this->Templates->param_names		= '"'.implode('", "', $param_names).'"';
		$this->Templates->param_contents	= '"'.implode('", "', $param_contents).'"';
	}

	/**
	 * Отображает страницу списка версий
	 */
	public function on_versions()
	{
		$this->Templates->set_page("Версии");

		$fields = array(
			'name'		=> array(),
			'user_id'	=> array()
		);

		$fields = $this->EasyForms->fields($fields, array('require' => false));

		if (!empty($fields['name']))
		{
			$fields['name'] = $this->Templates->clear_name($fields['name']);

			if (strrchr($fields['name'], '$') === '$')
				$fields['final'] = true;

			$fields['name'] = trim($fields['name'], "/$ ");
		}

		if (!empty($fields['user_id']))
		{
			$user = $this->Admin->get_by("id", $fields['user_id']);
			if ($user !== false)
				$this->Templates->login = $user['login'];
			else
				unset($fields['user_id']);
		}

		$this->Templates->bind_params($fields);

		$result = $this->PagesVersions->get_all($fields);
		if ($result === false)
			return;

		$params = $this->Templates->get_params(array("version", "date"), false);
		if ($params === false)
			$this->Log->error("Can't find versions params");

		list($version, $date) = $params;

		$last_date = 0;
		$versions = "";
		while (($row = $result->fetch()))
		{
			$cur_date = intval($row['time'] / 86400);

			if ($cur_date != $last_date)
			{
				$last_date = $cur_date;

				$month = date("n", $row['time']);
				$month = $this->Date->get_month_name($month, "genetive");

				$date->time = date("j", $row['time'])." ".$month." ".date("Y", $row['time']);

				$versions .= (string) $date;
			}

			if (!empty($filter['name']) && $row['name'] == $filter['name'])
				$row['name'] = "";

			$row['size'] = strlen($row['content']) + strlen($row['params']);

			$row['rollable'] = true;
			$row['restore_id'] = "";

			switch ($row['action'])
			{
				case "move":
				case "create":
					$row['rollable'] = false;
					break;
				case "restore":
					$row['rollable'] = false;
				$row['restore_id'] = $row['data'];
					break;
			}

			$version->bind_params($row);

			$version->action_letter = $this->version_actions[$row['action']];

			$versions .= (string) $version;
		}

		$this->Templates->versions = $versions;
	}

	/**
	 * Отображает страницу добавления
	 */
	public function on_add()
	{
		$this->Templates->set_page("Редактирование");
		$this->Templates->add("Шаблоны/Создание");

		$name = $this->EasyForms->field("name");
		$name = $this->Templates->clear_name($name);
		$name = addslashes($name);

		$this->Templates->field_name = $name;
	}

	/**
	 * Выполняет добавление
	 */
	public function on_add_submit()
	{
		$this->Templates->set_page("Редактирование");
		$this->Templates->add("Шаблоны/Создание");

		$fields = array(
			'name'			=> array(),
			'content'		=> array('require' => false),
			'param_names'		=> array('array' => true, 'caption' => "Имя параметра", 'ids' => "param_ids"),
			'param_contents'	=> array('array' => true, 'caption' => "Данные параметра", 'ids' => "param_ids", 'require' => false),
		);

		$fields = $this->EasyForms->fields($fields);
		if (!$fields)
			return;

		$this->parse_params($fields);

		$fields['name'] = $this->Templates->clear_name($fields['name']);

		if ($this->Pages->exist_raw($fields['name']))
		{
			$this->Errors->add("simple", array('name' => "Страница с таким именем уже существует"));
			$this->EasyForms->bind($fields);
			$this->bind_params($fields['params']);
			return;
		}

		$fields['user_id'] = $_SESSION['id'];
		$fields['created'] = time();
		$fields['updated'] = time();

		$this->Pages->add($fields);

		$fields['time'] = time();
		$fields['action'] = "create";

		if (!empty($fields['params']))
			$fields['params'] = serialize($fields['params']);
		else
			$fields['params'] = "";

		unset($fields['created']);
		unset($fields['updated']);

		$this->PagesVersions->add($fields);

		$this->Pages->update_names(array($fields['name']), array());

		$this->on_edit();
		$this->update_menu(array($fields['name']), array());
	}

	/**
	 * Отображает страницу редактирования
	 */
	public function on_edit($name = false)
	{
		$this->Templates->set_page("Редактирование");
		$this->Templates->add("Шаблоны/Редактирование");

		if ($name === false)
			$name = $this->EasyForms->field("name");
		if ($name === false)
			$this->Log->error("No page name specified");

		$name = $this->Templates->clear_name($name);

		$row = $this->Pages->get_raw($name);

		$this->bind_info($row);

		$row['name'] = addslashes($row['name']);
		$row['content'] = htmlspecialchars($row['content']);

		$this->Templates->field_orig_name = $row['name'];
		$this->Templates->field_name = $row['name'];
		$this->Templates->field_content = $row['content'];
	}

	/**
	 * Сохраняет изменения страницы
	 */
	public function on_edit_submit()
	{
		$this->Templates->set_page("Редактирование");
		$this->Templates->add("Шаблоны/Редактирование");

		$fields = array(
			'name'			=> array(),
			'content'		=> array('require' => false),
			'param_names'		=> array('array' => true, 'caption' => "Имя параметра", 'ids' => "param_ids"),
			'param_contents'	=> array('array' => true, 'caption' => "Данные параметра", 'ids' => "param_ids", 'require' => false),
			'orig_name'		=> array(),
		);

		$fields = $this->EasyForms->fields($fields);
		if (!$fields)
			return;

		$this->parse_params($fields);

		$fields['name']		= $this->Templates->clear_name($fields['name']);
		$fields['orig_name']	= $this->Templates->clear_name($fields['orig_name']);

		if (!$this->Pages->exist_raw($fields['orig_name']))
		{
			$this->on_add_submit();
			return;
		}

		$row = $this->Pages->get_raw($fields['orig_name']);

		$fields = array_merge($row, $fields);

		if ($fields['name'] != $fields['orig_name'] && $this->Pages->exist_raw($fields['name']))
		{
			$this->Errors->add("simple", array('name' => "Страница с таким именем уже существует"));

			$this->EasyForms->bind($fields);
			$this->bind_info($row);
			return;
		}

		if (!$this->check_changed($fields, $row, array('name', 'content', 'params')))
		{
			$this->Pages->invalidate($row['name']);
			$this->on_edit();
			return;
		}

		$orig_name = $fields['orig_name'];
		unset($fields['orig_name']);

		$fields['updated'] = time();
		$fields['user_id'] = $_SESSION['id'];

		$this->Pages->update($fields, $orig_name);

		$fields['time'] = time();

		if (!empty($fields['params']))
			$fields['params'] = serialize($fields['params']);
		else
			$fields['params'] = "";

		if ($fields['name'] != $orig_name)
		{
			$fields['action'] = "move";
			$fields['data'] = $orig_name;
		}
		else
		{
			$fields['action'] = "edit";
			$fields['data'] = "";
		}

		unset($fields['created']);
		unset($fields['updated']);

		$this->PagesVersions->add($fields);

		$this->Pages->update_names(array($fields['name']), array($orig_name));

		$this->on_edit();
		$this->update_menu(array($fields['name']), array($orig_name));
	}

	/**
	 * Отображает страницу переименовывания
	 */
	public function on_rename()
	{
		$this->Templates->set_page("Переименование");

		$name = $this->EasyForms->field("name");
		if ($name === false)
			$this->Log->error("No page name specified");

		$name = $this->Templates->clear_name($name);

		$this->Templates->field_orig_name = $name;
		$this->Templates->field_name = $name;

		$this->list_rename_pages($name);
	}

	/**
	 * Выполняет переименование
	 */
	public function on_rename_submit()
	{
		$this->Templates->set_page("Переименование");

		$fields = array(
			'name'		=> array('require' => false),
			'orig_name'	=> array(),
			'replace'	=> array('require' => false),
		);

		$fields = $this->EasyForms->fields($fields);
		if (!$fields)
			$this->Log->error("No page name specified");

		$fields['name']		= $this->Templates->clear_name($fields['name']);
		$fields['orig_name']	= $this->Templates->clear_name($fields['orig_name']);

		if (empty($fields['name']))
			$this->Errors->add("simple", array('name' => "Не задано новое имя"));
		else if ($fields['name'] == $fields['orig_name'])
			$this->Errors->add("simple", array('name' => "Новое и старое имя совпадают"));

		if (!$this->Errors->is_empty())
		{
			$this->list_rename_pages($fields['orig_name']);
			$this->EasyForms->bind($fields);
			return;
		}

		$names_exist	= $this->get_names($fields['name']);
		$names_orig	= $this->get_names($fields['orig_name']);

		$orig_len = strlen($fields['orig_name']);

		$names_renamed = array();
		while (list($key, $name) = each($names_orig))
			$names_renamed[$key] = $fields['name'].substr($name, $orig_len);

		$names_common = array_intersect($names_exist, $names_renamed);

		while (list(, $name) = each($names_common))
			$this->Errors->add("simple", array('name' => "Страница с именем {$name} уже существует"));

		if (!$this->Errors->is_empty())
		{
			$this->list_rename_pages($fields['orig_name']);
			$this->EasyForms->bind($fields);
			return;
		}

		$result = $this->Pages->get_all($fields['orig_name']);

		while (($row = $result->fetch()))
		{
			if (!$this->Templates->starts_with($row['name'], $fields['orig_name']))
				continue;

			$orig_name = $row['name'];
			$new_name = $fields['name'].substr($row['name'], $orig_len);

			$row['name']	= $new_name;
			$row['time']	= time();
			$row['user_id']	= $_SESSION['id'];
			$row['action']	= "move";
			$row['data']	= $orig_name;

			unset($row['created']);
			unset($row['updated']);

			$this->PagesVersions->add($row);

			$this->Pages->rename($orig_name, $new_name);
		}

		$this->Pages->update_names($names_renamed, $names_orig);

		$this->on_main();
		$this->update_menu($names_renamed, $names_orig);
	}

	/**
	 * Отображает страницу удаления
	 */
	public function on_delete()
	{
		$this->Templates->set_page("Удаление");

		$name = $this->EasyForms->field("name");
		$name = $this->Templates->clear_name($name);

		$names = $this->get_names($name);

		$item = $this->Templates->item;
		if ($item === false)
			$this->Log->error("Can't find 'item' param");

		$index = 0;
		$items = "";
		while (list(, $value) = each($names))
		{
			$item->name = $value;
			$item->index = $index++;

			$items .= (string) $item;
		}

		$this->Templates->name = $name;
		$this->Templates->items = $items;
	}

	/**
	 * Выполняет удаление
	 */
	public function on_delete_submit()
	{
		$fields = array(
			'names' => array('array' => true),
		);

		$fields = $this->EasyForms->fields($fields);
		if (!$fields)
			$this->Log->error("No page names specified");

		if (empty($fields['names']))
		{
			$this->on_delete();
			return;
		}

		$result = $this->Pages->get_all($fields['names']);

		while (($row = $result->fetch()))
		{
			$row['time']	= time();
			$row['user_id']	= $_SESSION['id'];
			$row['action']	= "delete";

			unset($row['created']);
			unset($row['updated']);

			$this->PagesVersions->add($row);
		}

		$this->Pages->delete($fields['names']);
		$this->Pages->update_names(array(), $fields['names']);

		$this->on_delete();
		$this->update_menu(array(), $fields['names']);
	}

	/**
	 * Отображает страницу экспорта
	 */
	public function on_export()
	{
		$this->Templates->set_page("Экспорт");

		$name = $this->EasyForms->field("name");
		$name = $this->Templates->clear_name($name);

		$names = $this->get_names($name);

		$item = $this->Templates->item;
		if ($item === false)
			$this->Log->error("Can't find 'item' param");

		$methods = $this->Compress->get_supported_methods();
		$this->Templates->bind_params($methods);

		$index = 0;
		$items = "";
		while (list(, $value) = each($names))
		{
			$item->name = $value;
			$item->index = $index++;

			$items .= (string) $item;
		}

		$this->Templates->name = $name;
		$this->Templates->items = $items;
	}

	/**
	 * Отображает данные экспорта страниц
	 */
	public function on_export_submit()
	{
		$fields = array(
			'names'		=> array('array' => true),
			'compress'	=> array('require' => false),
			'trim'		=> array('require' => false),
		);

		$fields = $this->EasyForms->fields($fields);
		if (!$fields)
			$this->Log->error("No page names specified");

		if (empty($fields['compress']))
			$fields['compress'] = "none";

		$fields['trim'] = intval($fields['trim']);

		$export = array();

		if (!empty($fields['names']))
		{
			$result = $this->Pages->get_all($fields['names']);

			while (($row = $result->fetch()))
			{
				if ($fields['trim'] != 0)
				{
					$pieces = explode("/", $row['name']);
					$pieces = array_slice($pieces, $fields['trim']);

					$row['name'] = implode("/", $pieces);
				}

				$data = $this->Common->copy_fields($row, array("name", "params", "content"));
				$this->Common->remove_empty($data);

				array_push($export, $data);
			}
		}

		$export = serialize($export);
		$export = $this->Compress->compress($export, $fields['compress']);

		header("Content-Length: ".strlen($export));
		header("Content-Disposition: attachment; filename=\"export.itw\"");
		header("Content-Transfer-Encoding: binary");

		Component::print_headers("application/octet-stream", false);

		echo $export;
		exit;
	}

	/**
	 * Отображает страницу импорта
	 */
	public function on_import()
	{
		$this->Templates->set_page("Импорт");

		$name = $this->EasyForms->field("name");
		$name = $this->Templates->clear_name($name);

		$this->Templates->name = $name;
	}

	/**
	 * Выполняет импорт страницы
	 */
	public function on_import_submit()
	{
		$this->XML->start_xml();

		$fields = array(
			'name'		=> array(),
			'replace'	=> array(),
		);

		$fields = $this->EasyForms->fields($fields, array('require' => false));

		$fields['name'] = $this->Templates->clear_name($fields['name']);

		if (empty($fields['replace']))
			$fields['replace'] = false;

		try
		{
			if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name']))
				throw new Exception("Не указан импортируемый файл");

			$content = file_get_contents($_FILES['file']['tmp_name']);
			$content = $this->Compress->decompress($content);

			$content = @unserialize($content);
			if ($content === false || !is_array($content))
				throw new Exception("Файл имеет неверный формат");

			$prefix = "";
			if (!empty($fields['name']))
				$prefix = $fields['name']."/";

			$names_added = array();

			reset($content);
			while (list($key, $data) = each($content))
			{
				if (!isset($data['name']))
					throw new Exception("Файл имеет неверный формат");

				if (!isset($data['content']))
					$data['content'] = "";

				if (isset($data['params']))
				{
					$data['params'] = @unserialize($data['params']);
					if ($data['params'] === false)
						throw new Exception("Файл имеет неверный формат");
				}
				else
					$data['params'] = array();

				$data['name'] = $this->Templates->clear_name($data['name']);
				$data['name'] = $prefix.$data['name'];

				array_push($names_added, $data['name']);

				$content[$key] = $data;
			}
		}
		catch (Exception $e)
		{
			$this->Errors->add("simple", array('file' => $e->getMessage()));
			$this->on_import();
			return;
		}

		$names_exist = $this->get_names($fields['name']);
		$names_common = array_intersect($names_exist, $names_added);

		$replaces = array();

		if (!$fields['replace'])
		{
			while (list(, $name) = each($names_common))
				$this->Errors->add("simple", array('name' => "Страница с именем {$name} уже существует"));

			if (!$this->Errors->is_empty())
			{
				$this->on_import();
				return;
			}
		}
		else
		{
			while (list(, $name) = each($names_common))
				$replaces[$name] = true;
		}

		reset($content);
		while (list(, $data) = each($content))
		{
			$data['user_id'] = $_SESSION['id'];
			$data['created'] = time();
			$data['updated'] = time();

			if (isset($replaces[$data['name']]))
			{
				$this->Pages->replace($data);
				$data['action'] = "edit";
			}
			else
			{
				$this->Pages->add($data);
				$data['action'] = "create";
			}

			$data['time'] = time();

			if (!empty($data['params']))
				$data['params'] = serialize($data['params']);
			else
				$data['params'] = "";

			unset($data['created']);
			unset($data['updated']);

			$this->PagesVersions->add($data);
		}

		$this->Pages->update_names($names_added, array());

		$this->on_main();
		$this->update_menu($names_added, array());
	}

	/**
	 * Выводит меню
	 */
	public function on_get_menu()
	{
		$fields = array(
			'prefix' => array(),
			'filter' => array()
		);

		$fields = $this->EasyForms->fields($fields, array('require' => false));

		$fields['prefix'] = $this->Templates->clear_name($fields['prefix']);

		$this->gen_menu($fields['prefix'], $fields['filter']);
	}

	/**
	 * Возвращает состояние страницы в виде данных в формате XML
	 */
	public function on_get_state()
	{
		$name = $this->EasyForms->field("name");
		if ($name === false)
			$this->XML->send_empty();

		$name = $this->Templates->clear_name($name);

		$names = $this->get_names($name);
		if (empty($names))
			$this->XML->send_empty();

		$xml = $this->XML->start_answer();
		$xml->addChild("exist");

		while (list(, $value) = each($names))
		{
			if ($value == $name)
				continue;

			$this->XML->send_xml($xml);
		}

		$xml->addChild("final");
		$this->XML->send_xml($xml);
	}

	private function list_rename_pages($prefix)
	{
		$item = $this->Templates->item;
		if ($item === false)
			$this->Log->error("Can't find 'item' param");

		$names = $this->get_names($prefix);

		$items = "";
		while (list(, $name) = each($names))
		{
			$item->name = $name;

			$items .= (string) $item;
		}

		$this->Templates->items = $items;
	}

	private function get_names($prefix)
	{
		$names = $this->Pages->get_names();

		if (empty($prefix))
			return $names;

		$filtered = array();

		reset($names);
		while (list(, $name) = each($names))
		{
			if ($this->Templates->starts_with($name, $prefix))
			{
				array_push($filtered, $name);
				continue;
			}

			if (!empty($filtered))
				break;
		}

		return $filtered;
	}

	private function parse_params(&$fields)
	{
		$fields['params'] = array();

		reset($fields['param_names']);
		while (list($i, $name) = each($fields['param_names']))
			$fields['params'][$name] = $fields['param_contents'][$i];

		unset($fields['param_names']);
		unset($fields['param_contents']);
	}

	private function bind_params(&$params)
	{
		if (empty($params))
			return;

		ksort($params);

		$param_names = array();
		$param_contents = array();

		reset($params);
		while (list($name, $value) = each($params))
		{
			$name = addslashes($name);

			$value = addslashes($value);
			$value = str_replace("\r", "", $value);
			$value = str_replace("\n", "\\n", $value);
			$value = str_replace("<", "\\<", $value);
			$value = str_replace(">", "\\>", $value);

			array_push($param_names, $name);
			array_push($param_contents, $value);
		}

		$this->Templates->field_param_names	= '"'.implode('", "', $param_names).'"';
		$this->Templates->field_param_contents	= '"'.implode('", "', $param_contents).'"';
	}

	private function bind_info($page)
	{
		$user = $this->Admin->get_by("id", $page['user_id']);
		if ($user !== false)
			$this->Templates->login = $user['login'];
		else
			$this->Templates->login = "Удалён";

		$this->bind_params($page['params']);

		$this->Templates->created = $page['created'];
		$this->Templates->updated = $page['updated'];
		$this->Templates->user_id = $page['user_id'];

		$this->Templates->content_size = strlen($page['content']);
		$this->Templates->params_size = strlen(serialize($page['params']));
		$this->Templates->params_count = count($page['params']);
	}

	private function gen_menu($prefix, $filter)
	{
		$names = $this->get_names($prefix);

		$structure = array();
		while (list(, $name) = each($names))
		{
			if ($filter != "" && mb_stripos($name, $filter) === false)
				continue;

			if ($prefix != "")
				$name = substr($name, strlen($prefix) + 1);

			$path = explode("/", $name);
			$max_level = count($path) - 1;

			$name = $prefix;

			$branch = &$structure;
			while (list($level, $piece) = each($path))
			{
				if ($level != 0 && $filter == "")
					break;

				if ($name != "")
					$name .= "/";
				$name .= $piece;

				if (isset($branch[$piece]))
				{
					$branch = &$branch[$piece];

					$branch['final'] = false;
					$branch = &$branch['sub'];
					continue;
				}

				$branch[$piece] = array(
					'sub'		=> array(),
					'name'		=> $name,
					'final'		=> ($level == $max_level),
					'editable'	=> ($level == $max_level)
				);

				$branch = &$branch[$piece]['sub'];
			}
		}

		$xml = $this->XML->start_answer();

		$this->traverse_menu($structure, $xml);

		$this->XML->send_xml($xml);
	}

	private function traverse_menu($names, &$xml)
	{
		while (list($caption, $params) = each($names))
		{
			$element = $xml->addChild("element");

			if (!empty($params['sub']))
				$this->traverse_menu($params['sub'], $element);

			$element->addAttribute("name", $params['name']);
			$element->addAttribute("caption", $caption);

			if ($params['final'])
				$element->addAttribute("final", "1");
			if ($params['editable'])
				$element->addAttribute("editable", "1");
		}
	}

	private function check_changed(&$old, &$new, $fields)
	{
		$fields_count = count($fields);

		for ($i = 0; $i < $fields_count; $i++)
		{
			$name = $fields[$i];

			if (isset($old[$name]) != isset($new[$name]))
				return true;
			if (!isset($old[$name]) && !isset($new[$name]))
				continue;

			if (!is_array($old[$name]) && !is_array($new[$name]))
			{
				if ($new[$name] != $old[$name])
					return true;
				continue;
			}

			if (is_array($old[$name]) && is_array($new[$name]))
			{
				$diff = array_diff_assoc($old[$name], $new[$name]);
				if (!empty($diff))
					return true;

				$diff = array_diff_assoc($new[$name], $old[$name]);
				if (!empty($diff))
					return true;

				continue;
			}
		}

		return false;
	}

	private function update_menu($added_pages, $deleted_pages)
	{
		$this->Templates->add("Шаблоны/Обновление меню");

		$common_pages = array_intersect($added_pages, $deleted_pages);

		$added_pages = array_diff($added_pages, $common_pages);
		$deleted_pages = array_diff($deleted_pages, $common_pages);

		if (!empty($added_pages))
			$this->Templates->added_pages = "'".implode("', '", $added_pages)."'";

		if (!empty($deleted_pages))
			$this->Templates->deleted_pages = "'".implode("', '", $deleted_pages)."'";
	}
}

?>