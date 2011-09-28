<?php

/**
 * Выполняет загрузку модуля, содержащий требуемый класс
 * @param $name String: Имя класса
 * @version 1.0.4
 */
function __autoload($name)
{
	if (preg_match("/^(?:Object)?([A-Z][a-zA-Z0-9_]*?)(?:Interface)?$/", $name, $matches) == 0)
		die("Trying to load unknown class {$name}");

	$class_name = strtolower($matches[1]);

	require_once MAIN_LOCATION."objects/".$class_name."/".$class_name.".inc.php";
}

/**
 * Базовый класс для компонентов
 *
 * @uses ObjectLog
 * @uses Objects
 *
 * @version 1.0.1
 */
abstract class Component
{
	private $objects;

	/**
	 * Конструктор класса
	 * @param $copy Component: Экземпляр класса для копирования
	 */
	public function __construct(&$copy = null)
	{
		if ($copy === null)
			$this->objects = new Objects();
		else
			$this->objects = &$copy->objects;
	}

	/**
	 * Выполняет инициализацию модуля
	 */
	public function initialize()
	{}

	/**
	 * Возвращает экземпляр объекта по имени
	 * @param $name String: Имя объекта
	 * @return Экземпляр объекта
	 * @retval Object
	 */
	public function __get($name)
	{
		return $this->objects->$name;
	}

	/**
	 * Выводит результат работы компонента
	 */
	public function output()
	{
		Component::print_headers();

		$this->objects->call("handler_bind");
		$this->objects->call("handler_output");
	}

	/**
	 * Выполняет действие имя которого задаётся параметром при запросе
	 * @param $default String: Имя действия по-умолчанию, если оно не задано в параметрах
	 */
	public function action($default = "main")
	{
		$action = $this->get_action();

		if (!empty($action))
			$method = "on_".$action;
		else
			$method = "on_".$default;

		if (!$this->is_provided($method))
			$this->Log->error("No action handler {$action} exists");

		return call_user_func(array(&$this, $method));
	}

	/**
	 * Возвращает флаг доступности метода в классе
	 * @param $method String: Требуемый метод
	 * @return Флаг доступности
	 * @retval true Класс предоставляет метод $method
	 * @retval false Класс НЕ предоставляет метод $method
	 */
	protected function is_provided($method)
	{
		return method_exists($this, $method);
	}

	private function get_action()
	{
		$action = "";
		if (isset($_POST['action']))
			$action = $_POST['action'];
		if ($action == "" && isset($_GET['action']))
			$action = $_GET['action'];

		$action	= urldecode($action);
		$action = trim($action, "/ ");

		if (preg_match("/[^a-z0-9_]/", $action) != 0)
			return "";

		return $action;
	}

	/**
	 * Перенаправляет пользователя на другой url
	 * @param $url String: URL для перенаправления
	 * @param $params Array: Массив параметров для GET запроса
	 * @param $code Integer: Код ответа клиента серверу
	 */
	static public function redirect($url, $params = array(), $code = 301)
	{
		$url = ltrim($url, "/");

		$get_vars = "";
		while (list($name, $value) = each($params))
			$get_vars .= $name."=".urlencode($value)."&";

		if ($get_vars != "")
			$url = $url."?".trim($get_vars, "&");

		if (strpos($url, "http://") !== 0)
			$url = "/".$url;

		header("Location: ".$url, true, $code);
		exit;
	}

	/**
	 * Отправляет заголовки проотокола HTTP клиенту
	 * @param $type String: Тип содержимого
	 * @param $print_charset Boolean: Определяет, требуется ли указание кодировки документа в заголовках
	 */
	static public function print_headers($type = "text/html", $print_charset = true)
	{
		if ($print_charset)
			header("Content-type: {$type}; charset=\"".SITE_CHARSET."\"");
		else
			header("Content-type: {$type}");

		header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
	}

	/**
	 * Выполняет загрузку всех необходимых классов и инициализирует менеджер выбранного компонента
	 * @param $prefix String: Префикс имён классов
	 * @param $work_dir String: Рабочая директория компонентов
	 * @return Экземпляр класса менеджера компонентов
	 * @retval Component
	 */
	static public function init($prefix = "", $work_dir = "")
	{
		require "config.inc.php";

		require_once "objects/object.inc.php";

		mb_internal_encoding(SITE_CHARSET);

		if ($work_dir !== "")
			chdir($work_dir);

		require_once "manager.inc.php";

		$class_name = "Manager".$prefix;

		return new $class_name();
	}
}

?>