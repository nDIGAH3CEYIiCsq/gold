<?php

/**
 * Менеджер компонентов пользователя
 *
 * @uses Component
 * @uses ObjectCache
 * @uses ObjectComponents
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectPages
 * @uses ObjectTemplates
 * @uses TreeLeaf
 *
 * @version 1.0.3
 */
class ManagerUser extends Component
{
	const CacheClass = "components_user";

	private $prefixes = array();

	private $default = "";

	private $actions = array(
		'ignore'	=> 0,
		'block'		=> 1,
		'data'		=> 5,
		'page'		=> 10,
		'bind'		=> 20,
		'default'	=> 60
	);

	public $handlers;

	public function __construct(&$copy = null)
	{
		parent::__construct($copy);

		$this->Components->init($this, "User");

		$this->prefix();
		$this->load();
		$this->action();
		$this->output();
	}

	/**
	 * Обработчик, вызываемый при открытии страниц
	 */
	public function on_main()
	{
		$url = $this->EasyForms->field("url", INPUT_GET);
		$url = $this->Templates->clear_name($url);

		$path = $this->update_path($url);
		$components = $this->handlers->get_binds($path);

		$block = true;
		while (list(, $data) = each($components))
		{
			$data['page']	= $url;
			$data['domain']	= $this->get_domain_name();
			$data['path']	= $this->strip_path($data['path']);

			if ($data['path'] != "")
				$data['postfix'] = substr($data['page'], strlen($data['path']) + 1);
			else
				$data['postfix'] = $data['page'];

			$next_action = $this->call_handlers($data);

			if ($next_action == "block")
				break;
			if ($next_action == "nopage")
				continue;

			$block = false;

			if ($next_action == "break")
				break;
			if ($next_action == "return")
				return;
		}

		if (!$block)
			return;

		$this->block();
	}

	private function prefix()
	{
		$domain_prefix = $this->get_domain_prefix();
		if ($domain_prefix != "")
			$domain_prefix = "/".$domain_prefix;

		$base_path = SITE_BASE_PATH.$domain_prefix;

		$template = SITE_DEFAULT_TEMPLATE;
		if ($base_path != "")
			$template = "/".$template;

		$this->Templates->set_base_path($base_path);
		$this->Templates->set_template($base_path.$template);
	}

	private function block()
	{
		if (!$this->Templates->exist("/Ошибки/404"))
			Component::redirect("");

		header("Not Found", true, 404);

		if (empty($_SERVER['REDIRECT_URL']))
			$redirect_url = "/";
		else
			$redirect_url = $_SERVER['REDIRECT_URL'];

		$template = $this->Templates->get("/Ошибки/404");
		$template->REDIRECT_URL = htmlspecialchars($redirect_url, ENT_QUOTES);

		echo (string) $template;
		exit;
	}

	private function update_path(&$path)
	{
		if ($path == "")
			$path = SITE_DEFAULT_PAGE;

		$domain_prefix = $this->get_domain_prefix();
		if ($domain_prefix != "")
			$domain_prefix = $domain_prefix."/";

		return $domain_prefix.$path;
	}

	private function strip_path($path)
	{
		$domain_prefix = $this->get_domain_prefix();
		if ($domain_prefix == "")
			return $path;

		$domain_prefix = $domain_prefix."/";

		if (strpos($path, $domain_prefix) !== 0)
			return $path;

		return substr($path, strlen($domain_prefix));
	}

	private function get_domain_name()
	{
		$point_pos = strpos($_SERVER['HTTP_HOST'], ".");
		if ($point_pos == 0)
			return "";

		return substr($_SERVER['HTTP_HOST'], 0, $point_pos);
	}

	private function get_domain_prefix()
	{
		$domain_name = $this->get_domain_name();
		if ($domain_name == "")
			return $this->default;

		if (!isset($this->prefixes[$domain_name]))
			return $this->default;

		return $this->prefixes[$domain_name];
	}

	private function call_handlers($data)
	{
		while (list(, $method) = each($data['methods']))
		{
			$next_action = $this->Components->call($data['module'], "on_".$method, array($data));
			if ($next_action == "")
				continue;

			return $next_action;
		}
	}

	private function load()
	{
		$handlers = $this->Cache->get("handlers", self::CacheClass);
		if ($handlers !== false)
		{
			$this->handlers = $handlers;
			return;
		}

		$this->handlers = new TreeLeaf();

		$result = $this->Pages->get_all("Обработчики/");

		$components = "";
		while (($row = $result->fetch()))
			$components .= $row['content'];

		$template = $this->Templates->get("/Обработчики");
		$template->components = $components;

		$xml = simplexml_load_string((string) $template);

		$bindings = array();
		foreach ($xml->children() as $component)
		{
			$name = $component->getName();
			if ($name != "component")
				$this->Log->error("Unknown element {$name}");

			if (!isset($component['name']))
				$this->Log->error("No component name is set");

			$name = (string) $component['name'];

			$bindings[$name] = $component;
		}

		reset($bindings);
		while (list(, $component) = each($bindings))
			$this->parse_binds($component);

		$this->Cache->set("handlers", self::CacheClass, $this->handlers);
	}

	private function parse_binds($component)
	{
		$component_name = (string) $component['name'];

		foreach ($component->children() as $bind)
		{
			$name = $bind->getName();
			if ($name != "bind")
				$this->Log->error("Unknown element {$name}");

			if (!isset($bind['method']))
				$this->Log->error("No method name for binding");

			$this->parse_methods($bind, $component_name);
		}
	}

	private function parse_methods($bind, $component_name)
	{
		$methods = (string) $bind['method'];
		$methods = explode(",", $methods);
		$methods = array_map("trim", $methods);
		$methods = array_unique($methods);

		if (empty($methods))
			return;

		if (!isset($bind['action']))
			$bind['action'] = "DEFAULT";

		$priority = $this->get_priority($bind['action']);
		if ($priority == 0)
			return;

		if (isset($bind['path']))
		{
			$name = (string) $bind['path'];
			$name = $this->Templates->clear_name($name);

			$params = $this->parse_params($bind);

			$this->handlers->add_methods($component_name, $methods, $priority, $name, $params);
			return;
		}

		foreach ($bind->children() as $path)
		{
			$element_name = $path->getName();
			if ($element_name != "path")
				continue;

			if (!isset($path['name']))
				$this->Log->error("Path name is not set");

			$name = (string) $path['name'];
			$name = $this->Templates->clear_name($name);

			$params = $this->parse_params($path);

			$this->handlers->add_methods($component_name, $methods, $priority, $name, $params);
		}
	}

	private function parse_params($xml)
	{
		$data = array();

		foreach ($xml->children() as $element)
		{
			$name = $element->getName();

			$value = trim((string) $element);
			$attributes = $this->parse_attributes($element);
			$child = $this->parse_params($element);

			$filled = !empty($attributes) + !empty($child);
			switch ($filled)
			{
				case 2:
					$params = array('value' => $value, 'attributes' => $attributes, 'child' => $child);
					break;
				case 1:
					if (!empty($attributes))
						$params = $attributes;
					else
						$params = $child;
					break;
				case 0:
					$params = $value;
					break;
			}

			if (!isset($data[$name]))
				$data[$name] = $params;
			else if (is_array($data[$name]))
				$data[$name] = array($data[$name], $params);
			else
				$data[$name][] = $params;
		}

		return $data;
	}

	private function parse_attributes($xml)
	{
		$attributes = array();
		foreach($xml->attributes() as $name => $value)
			$attributes[$name] = (string) $value;

		return $attributes;
	}

	private function get_priority($action)
	{
		if (is_numeric($action))
			return $action;

		$action = strtolower($action);

		if (!isset($this->actions[$action]))
			return $this->actions['default'];

		return $this->actions[$action];
	}
}

/**
 * Реализует лист дерева
 *
 * @version 1.0.1
 */
class TreeLeaf
{
	private $modules = array();
	private $branches = array();

	/**
	 * Сравнивает биндинги по приоритету
	 * @param $a Array: Биндинг
	 * @param $b Array: Биндинг
	 * @retval Integer Результат сравнения
	 */
	static public function priority_sort($a, $b)
	{
		if ($a['priority'] == $b['priority'])
			return 0;
		return ($a['priority'] > $b['priority']) ? +1 : -1;
	}

	/**
	 * Возвращает список модулей, связанных с данным путём
	 * @param $path String: Путь
	 * @retval Array Список модулей
	 */
	public function get_binds($path)
	{
		$path = trim($path, "/");
		$path = trim($path."/$", "/");

		$pieces = explode("/", $path);
		$handlers = &$this;

		$modules = $handlers->merge_modules();

		while (list(, $piece) = each($pieces))
		{
			$handlers = $handlers->get($piece);
			if ($handlers === false)
				break;

			$modules = $handlers->merge_modules($modules);
		}

		usort($modules, array("TreeLeaf", "priority_sort"));

		return $modules;
	}

	/**
	 * Добавляет методы к указанному модулю в дереве страниц
	 * @param $module String: Имя модуля
	 * @param $methods Array: Добавляемые методы
	 * @param $priority Integer: Приоритет вызова методов
	 * @param $path String: Путь к которому привязываются методы
	 * @param $params Array: Параметры биндинга
	 */
	public function add_methods($module, $methods, $priority, $path, $params)
	{
		$leaf = &$this->get_last_leaf($path);

		$leaf->modules[$module] = array(
			'module'	=> $module,
			'methods'	=> $methods,
			'priority'	=> $priority,
			'path'		=> $path,
			'params'	=> $params
		);
	}

	private function &get_last_leaf($path)
	{
		if ($path == "")
			return $this;

		$handlers = &$this;

		$path = explode("/", $path);
		while (list(, $piece) = each($path))
		{
			$handler = $handlers->get($piece);
			if ($handler !== false)
			{
				$handlers = $handler;
				continue;
			}

			$handlers = &$handlers->add($piece);
		}

		return $handlers;
	}

	private function merge_modules($modules = array())
	{
		$values = array_values($this->modules);
		return array_merge($modules, $values);
	}

	private function &add($name)
	{
		if (isset($this->branches[$name]))
			return $this->branches[$name];

		$this->branches[$name] = new TreeLeaf();

		return $this->branches[$name];
	}

	private function get($name)
	{
		if (!isset($this->branches[$name]))
			return false;

		return $this->branches[$name];
	}
}

/**
 * Базовый класс для компонентов пользователя
 *
 * @uses Component
 * @uses ObjectLog
 *
 * @version 1.0.2
 */
abstract class ComponentUser extends Component
{
	/**
	 * Проверяет, все ли требуемые параметры переданы обработчику
	 * @param $params Array: Переданные параметры
	 * @param $fields Array: Требуемые параметры
	 */
	protected function check_params($params, $fields)
	{
		reset($fields);
		while (list(, $field) = each($fields))
		{
			if (!empty($params[$field]))
				continue;

			$this->Log->error("Missed required '{$field}' param");
		}
	}

	/**
	 * Возвращает постфикс пути
	 * @param $data Array: Данные обработчика
	 * @param $type String: Тип постфикса
	 * @retval Mixed Значение постфикса
	 */
	protected function get_postfix($data, $type)
	{
		switch ($type)
		{
			case "integer":
			{
				if (empty($data['postfix']))
					return 0;
				return intval($data['postfix']);
			}
			case "string":
			{
				if (empty($data['postfix']))
					return "";
				return $data['postfix'];
			}
			default:
				$this->Log->error("Unknown postfix type {$type}");
		}
	}
}


?>