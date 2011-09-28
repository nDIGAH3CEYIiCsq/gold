<?php

/**
 * Модуль управления пользователями панели администратора
 *
 * @uses ComponentAdmin
 * @uses ObjectAdmin
 * @uses ObjectEasyForms
 * @uses ObjectErrors
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @uses ObjectXML
 *
 * @version 1.0.4
 */
class AdminUsers extends ComponentAdmin
{
	private $owner;
	private $accesses;

	public function __construct(&$copy = null)
	{
		parent::__construct($copy);

		$this->owner = &$copy;
		$this->accesses = &$copy->accesses;
	}

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Пользователи");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница AJAX");
	}

	public function get_services()
	{
		return array('index' => "Пользователи");
	}

	public function get_access_overrides()
	{
		return array(
			'get_data'		=> "INDEX",
			'access_get'		=> "ACCESS",
			'access_set'		=> "ACCESS_CHANGE",
			'password_change'	=> array("PASSWORD", "PASSWORD_OTHERS"),
		);
	}

	/**
	 * Отображает титульную страницу модуля
	 */
	public function on_index()
	{
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
		$this->Templates->set_page("");
		$this->Templates->accesses = $this->make_access_options();
	}

	/**
	 * Отправляет данные о пользователях в JSON формате
	 */
	public function on_get_data()
	{
		$this->Admin->send_data();
	}

	/**
	 * Добавляет пользователя
	 */
	public function on_add()
	{
		$this->XML->start_xml("errors");

		$fields = array(
			'login'		=> array(),
			'email'		=> array(),
			'password'	=> array(),
			'password2'	=> array(),
			'access'	=> array('array' => true),
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		if ($fields['password'] != $fields['password2'])
		{
			$this->Errors->add("simple", array('password2' => "Пароль и подтверждение пароля не совпадают"));
			return;
		}

		$fields['access'] = $this->parse_access_list($fields['access']);

		if (!$this->Admin->check_access("USERS_ACCESS_CHANGE") && $this->check_access_grow($_SESSION['access'], $fields['access']))
		{
			$this->Errors->add("simple", array('access' => "Вы не можете назначить новому пользователю права, которыми вы не обладаете"));
			return;
		}

		unset($fields['password2']);

		$this->Admin->register($fields);
	}

	/**
	 * Изменяет данные пользователя
	 */
	public function on_edit()
	{
		$this->XML->start_xml("errors");

		$fields = array(
			'id'	=> array(),
			'login'	=> array('id' => "edit_login"),
			'email'	=> array('id' => "edit_email"),
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		$id = $fields['id'];
		unset($fields['id']);

		$this->Admin->update_user($id, $fields);
	}

	/**
	 * Удаляет пользователя
	 */
	public function on_delete()
	{
		$id = $this->EasyForms->field("id");
		if ($id === false)
			exit;

		$this->Admin->unregister($id);
		exit;
	}

	/**
	 * Меняет пароль пользователя
	 */
	public function on_password_change()
	{
		$this->XML->start_xml("errors");

		$fields = array(
			'id'			=> array(),
			'password_new'		=> array(),
			'password_repeat'	=> array(),
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		if (!$this->Admin->check_owner($fields['id']) && !$this->owner->access_check("PASSWORD_OTHERS"))
			return;

		if ($fields['password_new'] != $fields['password_repeat'])
		{
			$this->Errors->add("simple", array('password_repeat' => "Пароль и подтверждение пароля не совпадают"));
			return;
		}

		$this->Admin->change_password($fields['id'], "", $fields['password_new']);
	}

	/**
	 * Выводит список прав пользователя
	 */
	public function on_access_get()
	{
		$fields = array(
			'id'	=> array(),
			'edit'	=> array(),
		);

		$fields = $this->EasyForms->fields($fields, array('require' => false));

		$fields['id'] = $this->Admin->used_id($fields['id']);

		$row = $this->Admin->get_by("id", $fields['id']);
		if ($row === false)
			$this->XML->send_empty();

		if ($fields['edit'] && !$this->Admin->check_access("USERS_ACCESS_CHANGE"))
			$fields['edit'] = false;

		$accesses_user = unserialize($row['access']);

		$xml = $this->XML->start_answer();

		if ($fields['edit'] == "true")
		{
			$accesses_all = $this->get_all_accesses();
			$this->make_accesses($xml, $accesses_all, $accesses_user);
		}
		else
			$this->make_accesses($xml, $accesses_user);

		$this->XML->send_xml($xml);
	}

	/**
	 * Устанавливает права пользователя
	 */
	public function on_access_set()
	{
		$this->XML->start_xml("errors");

		$fields = array(
			'id'		=> array(),
			'access'	=> array('array' => true),
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		$fields['access'] = $this->parse_access_list($fields['access']);

		$this->Admin->change_access($fields['id'], $fields['access']);
	}

	private function check_access_grow($access_orig, $access_check)
	{
		reset($access_check);
		while (list($name, $value) = each($access_check))
		{
			if (!isset($access_orig[$name]))
				return true;

			$piece_orig = $access_orig[$name];
			$piece_check = $access_check[$name];

			if (!is_array($value))
				continue;

			if ($this->check_access_grow($piece_orig, $piece_check))
				return true;
		}

		return false;
	}

	private function make_access_options()
	{
		if (!$this->Admin->check_access("USERS_ACCESS"))
			return "";

		$params = $this->Templates->get_params(array("access_option", "access_optgroup"), false);
		if ($params === false)
			$this->Log->error("Can't find access params");

		list($access_option, $access_optgroup) = $params;

		$access_options = "";

		reset($this->accesses);
		while (list($module, $accesses) = each($this->accesses))
		{
			$module = strtoupper($module);
			$accesses = array_values($accesses);

			$unique_values = array();

			$options = "";

			reset($accesses);
			while (list(, $access) = each($accesses))
			{
				if ($access === false)
					continue;

				if (!is_array($access))
					$access = array($access);

				while (list(, $element) = each($access))
				{
					$value = $module."_".$element;

					if (isset($unique_values[$value]))
						continue;

					$access_option->value = $value;
					$access_option->access = $element;

					$options .= (string) $access_option;

					$unique_values[$value] = true;
				}
			}

			if ($options == "")
				continue;

			$access_optgroup->module = $module;
			$access_optgroup->options = $options;

			$access_options .= (string) $access_optgroup;
		}

		return $access_options;
	}

	private function get_all_accesses()
	{
		$accesses_list = array();

		reset($this->accesses);
		while (list($module, $accesses) = each($this->accesses))
		{
			$module = strtoupper($module);
			$accesses = array_values($accesses);

			reset($accesses);
			while (list(, $access) = each($accesses))
			{
				if ($access === false)
					continue;

				if (!is_array($access))
					$access = array($access);

				while (list(, $element) = each($access))
					array_push($accesses_list, $module."_".$element);
			}
		}

		$accesses_list = array_unique($accesses_list);
		$accesses_list = $this->parse_access_list($accesses_list);

		return $accesses_list;
	}

	private function parse_access_list($accesses_list)
	{
		$parsed = array();

		reset($accesses_list);
		while (list(, $access) = each($accesses_list))
		{
			$access = explode("_", $access);

			$current = &$parsed;

			$cnt = count($access);
			for ($i = 0; $i < $cnt; $i++)
			{
				$element = $access[$i];

				if (isset($current[$element]) && is_array($current[$element]))
				{
					$current = &$current[$element];
					continue;
				}

				if ($i == $cnt - 1)
					$current[$element] = true;
				else
					$current[$element] = array();

				$current = &$current[$element];
			}
		}

		return $parsed;
	}

	private function make_accesses(&$xml, $accesses_all, $accesses_user = false, $prefix = "")
	{
		$all_checked = true;

		reset($accesses_all);
		while (list($name, $values) = each($accesses_all))
		{
			$value = trim($prefix."_".$name, "_");

			$access = $xml->addChild("access");

			$access->addAttribute("title", $name);
			$access->addAttribute("value", $value);

			$checked = true;

			if ($accesses_user !== false && !$this->Admin->check_access($value, $accesses_user))
				$checked = false;

			if (is_array($values) && !$this->make_accesses($access, $values, $accesses_user, $value))
				$checked = false;

			$access->addAttribute("checked", $checked);

			$all_checked = $all_checked && $checked;
		}

		return $all_checked;
	}
}

?>