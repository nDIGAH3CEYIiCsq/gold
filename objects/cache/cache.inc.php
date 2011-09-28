<?php

/**
 * Предоставляет функции работы с кэшем
 *
 * @uses Memcached
 * @uses ObjectLog
 *
 * @version 1.0.2
 */
class ObjectCache extends Object
{
	private $memcache;

	public function __construct(&$objects)
	{
		parent::__construct($objects);

		$this->memcache = new Memcached();
		$this->memcache->addServer(CACHE_HOST, CACHE_PORT);
	}

	/**
	 * Возвращает данные из кэша
	 * @param $name String: Имя данных
	 * @param $class String: Класс данных
	 * @param $use_nocache Boolean: Определает, следует ли учитывать GET параметр nocache
	 * @return Данные из кэша
	 * @retval Mixed Если данные в кэше найдены
	 * @retval false Если данные в кэше не найдены
	 */
	public function get($name, $class, $use_nocache = true)
	{
		$key = $this->get_key($name, $class);

		if ($use_nocache && isset($_GET['nocache']))
		{
			$this->memcache->delete($key);
			return false;
		}

		return $this->memcache->get($key);
	}

	/**
	 * Помещает данные в кэш
	 * @param $name String: Имя данных
	 * @param $class String: Класс данных
	 * @param $value String: Значение данных
	 * @param $expire_time Integer: Время жизни данных в кэше
	 */
	public function set($name, $class, $value, $expire_time = 0)
	{
		$key = $this->get_key($name, $class);

		if ($this->memcache->set($key, $value, $expire_time) === false)
			$this->Log->warning("Can't set cache data for key {$key}");
	}

	/**
	 * Удаляет данные из кэша
	 * @param $name String: Имя данных
	 * @param $class String: Класс данных
	 */
	public function delete($name, $class)
	{
		$key = $this->get_key($name, $class);
		$this->memcache->delete($key);
	}

	/**
	 * Увеличивает значение переменной в кэше
	 * @param $name String: Имя переменной
	 * @param $class String: Класс переменной
	 * @return Новое значение переменной
	 * @retval Integer
	 */
	public function increment($name, $class)
	{
		$key = $this->get_key($name, $class);
		return $this->memcache->increment($key);
	}

	/**
	 * Генерирует уникальный ключ данных на основе имени и списка параметров
	 * @param $name String: Имя данных
	 * @param $params Array: Список параметров
	 * @param $page Array: Данные постраничной разбивки
	 * @return Уникальный ключ данных
	 * @retval String
	 */
	public function make_key($name, $params = array(), $page = false)
	{
		array_unshift($params, $name);

		if ($page !== false)
		{
			if (is_array($page))
				$page = $page['page'];
			array_push($params, $page);
		}

		return implode("_", $params);
	}

	/**
	 * Возвращает статистику кэша
	 * @return Данные статистики кэша
	 * @retval Array
	 */
	public function get_stats()
	{
		return $this->memcache->getstats();
	}

	private function get_key($name, $class)
	{
		$key = CACHE_HASH_PREFIX."|".$class."|".$name;
		$key = preg_replace("/[\\x00-\\x20]/ui", "", $key);
		$key = str_replace(" ", "_", $key);

		if (strlen($key) > 250)
			$this->Log->error("Key {$key} length larger than 250 symbols");

		return $key;
	}
}

?>