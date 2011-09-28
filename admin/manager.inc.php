<?php

/**
 * Менеджер компонентов панели администрирования
 *
 * @uses Component
 * @uses ObjectAdmin
 * @uses ObjectCache
 * @uses ObjectComponents
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 *
 * @version 1.0.4
 */
class ManagerAdmin extends Component
{
	const CacheClass = "components_admin";

	public $modules = array();
	public $accesses = array();

	public function __construct(&$copy = null)
	{
		parent::__construct($copy);

		$this->Components->init($this, "Admin");

		if ($this->Admin->is_authed())
		{
			$this->load_modules();
			$this->action();
		}
		else
			$this->login();

		$this->output();
	}

	/**
	 * Вызывает метод модуля панели администрирования
	 * @param $method String: Имя метода
	 * @param $args String: Аргументы метода
	 * @retval Mixed Результат работы метода
	 */
	public function __call($method, $args)
	{
		$module = self::get_module();

		if (!isset($this->accesses[$module]))
			$this->Log->error("Can't check module access");

		$accesses = $this->accesses[$module];

		$access = substr($method, 3);
		if (isset($accesses[$access]))
			$access = $accesses[$access];

		if (!$this->access_check($access))
			return;

		return $this->Components->call($module, $method, $args);
	}

	/**
	 * Проверяет наличие прав доступа у авторизированного пользователя к текущему модулю
	 * @param $access String: Требуемые права доступа
	 * @retval true Права доступа есть
	 * @retval false Прав доступа нет
	 */
	public function access_check($access)
	{
		if ($access === false)
			return true;

		if (!is_array($access))
			$access = array($access);

		$module = self::get_module();

		$accesses = array();
		while (list(, $element) = each($access))
		{
			$element = strtoupper($module."_".$element);

			if ($this->Admin->check_access($element))
				return true;

			array_push($accesses, $element);
		}

		$this->Templates->set("Панель администрирования/Шаблоны/Доступ запрещён");
		$this->Templates->accesses = implode(", ", $accesses);
		return false;
	}

	protected function is_provided($method)
	{
		return true;
	}

	private function load_modules()
	{
		$data = $this->Cache->get("data", self::CacheClass);
		if ($data !== false)
		{
			$this->modules = $data['modules'];
			$this->accesses = $data['accesses'];
			return;
		}

		$modules = $this->get_list();

		while (list(, $module) = each($modules))
		{
			$module_class = $this->Components->get_class_name($module);
			if ($module_class === false)
				continue;

			$class = new $module_class($this);

			$this->modules[$module] = $class->get_services();
			$this->accesses[$module] = $class->get_accesses($module_class);
		}

		$data = array();
		$data['modules'] = $this->modules;
		$data['accesses'] = $this->accesses;

		$this->Cache->set("data", self::CacheClass, $data);
	}

	private function get_list()
	{
		$modules = array();

		$dir_handle = @opendir(".");
		if ($dir_handle === false)
			$this->Log->error("Can't open current directory");

		while (($file = readdir($dir_handle)) !== false)
		{
			if ($file == "." || $file == "..")
				continue;

			if (!is_dir($file))
				continue;

			if (!file_exists($file."/".$file.".inc.php"))
				continue;

			$modules[] = $file;
		}

		return $modules;
	}

	private function login()
	{
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
		$this->Templates->set_page("Панель администрирования/Форма авторизации");

		if (!$this->EasyForms->field("send"))
			return;

		$fields = array(
			'login'		=> array(),
			'password'	=> array(),
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		if (!$this->Admin->login($fields['login'], $fields['password']))
		{
			$this->EasyForms->bind($fields);
			return;
		}

		Component::redirect($_SERVER['SCRIPT_NAME']);
	}

	/**
	 * Возвращает имя текущего модуля
	 * @retval String Имя модуля
	 */
	static public function get_module()
	{
		if (empty($_GET['module']))
			return "admin";

		$module = $_GET['module'];

		if (preg_match("/[^a-z0-9_]/i", $module) != 0)
			return "admin";

		return $module;
	}
}

/**
 * Базовый класс для компонентов панели администрирования
 *
 * @uses Component
 * @uses ObjectTemplates
 *
 * @version 1.0.3
 */
abstract class ComponentAdmin extends Component
{
	public function initialize()
	{
		parent::initialize();

		$this->Templates->session_id	= $_SESSION['id'];
		$this->Templates->session_login	= $_SESSION['login'];
		$this->Templates->admin_module	= ManagerAdmin::get_module();
	}

	public function get_accesses($module_class)
	{
		$methods = get_class_methods($module_class);

		$acccesses = array();
		while (list(, $method) = each($methods))
		{
			if (strpos($method, "on_") !== 0)
				continue;

			$method = substr($method, 3);
			$access = strtoupper($method);

			$acccesses[$method] = $access;
		}

		$overrides = $this->get_access_overrides();

		return array_merge($acccesses, $overrides);
	}

	/**
	 * Возвращает список сервисов, предоставляемых данным модулем
	 * @retval Array Список сервисов
	 *
	 * Пример возвращаемого значения:
	 * @code
	 * array(
	 *	'index' => "Управление страницами",
	 *	'world' => "Управление миром"
	 * );
	 * @endcode
	 */
	abstract public function get_services();

	/**
	 * Возвращает пользовательский массив прав доступа для каждого метода-обработчика
	 * @retval Array Массив прав доступа
	 *
	 * Пример возвращаемого значения:
	 * @code
	 * array(
	 *	'index' => "INDEX",
	 *	'delete' => array("DELETE", "RENAME")
	 * );
	 * @endcode
	 */
	abstract public function get_access_overrides();

	/**
	 * Перенаправляет пользователя на другой обработчик в данном модуле
	 * @param $action String: Обработчик в модуле
	 * @param $params Array: Массив дополнительных параметров для GET запроса
	 */
	protected function go_action($action, $params = array())
	{
		$module = ManagerAdmin::get_module();

		$request = array('module' => $module, 'action' => $action);
		$request = array_merge($request, $params);

		Component::redirect($_SERVER['SCRIPT_NAME'], $request);
		exit;
	}
}

?>