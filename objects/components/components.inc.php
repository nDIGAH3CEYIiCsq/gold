<?php

/**
 * Предоставляет функции вызова методов компонентов
 *
 * @uses ObjectLog
 *
 * @version 1.0.2
 */
class ObjectComponents extends Object
{
	private $instances = array();

	private $owner;
	private $prefix;

	/**
	 * Инициализирует экземпляр класса
	 * @param $owner Component: Владелец компонентов
	 * @param $prefix String: Префикс имён компонентов
	 */
	public function init($owner, $prefix)
	{
		$this->owner = $owner;
		$this->prefix = $prefix;
	}

	/**
	 * Загружает класс компонента и возвращает имя его класса
	 * @param $component String: Имя компонента
	 * @retval String Имя класса
	 */
	public function get_class_name($component)
	{
		$component_path = $this->get_path($component);
		if ($component_path === false)
			return false;

		require_once $component_path;

		$class_name = ucfirst($component);
		$class_name = preg_replace("/_(.?)/e", "ucfirst('\\1')", $class_name);
		$class_name = $this->prefix.$class_name;

		if (!class_exists($class_name, false))
			return false;

		return $class_name;
	}

	/**
	 * Возвращает экземпляр класса компонента
	 * @param $component String: Имя компонента
	 * @retval Component Экземпляр класса компонента
	 */
	public function get_instance($component)
	{
		if (isset($this->instances[$component]))
			return $this->instances[$component];

		$class_name = $this->get_class_name($component);
		if ($class_name === null)
			return null;

		$instance = new $class_name($this->owner);
		$instance->initialize();

		$this->instances[$component] = &$instance;

		return $instance;
	}

	/**
	 * Вызывает метод в компоненте
	 * @param $component String: Имя компонента
	 * @param $method String: Имя метода
	 * @param $args Array: Аргументы метода
	 * @retval Mixed Результат выполнения метода
	 */
	public function call($component, $method, $args = array())
	{
		$instance = $this->get_instance($component);
		if ($instance === null)
			$this->Log->error("Component {$component} doesn't exist ");

		if (!method_exists($instance, $method))
			$this->Log->error("Method {$component} :: {$method} doesn't exist");

		return call_user_func_array(array(&$instance, $method), $args);
	}

	private function get_path($component)
	{
		$component = preg_replace("/[^a-z0-9_]/i", "", $component);

		$component_path = $component."/".$component.".inc.php";
		if (!file_exists($component_path))
			return false;

		return $component_path;
	}
}

?>