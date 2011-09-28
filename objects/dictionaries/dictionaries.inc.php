<?php

/**
 * Предоставляет функции работы со словарями
 *
 * @uses DatabaseInterface
 * @uses Dictionary
 * @uses ObjectCache
 *
 * @version 1.0.2
 */
class ObjectDictionaries extends Object implements DatabaseInterface
{
	const CacheClass = "dictionaries";

	private $dicts_cache = array();

	static public function get_queries()
	{
		return array(
			'load_all'	=> "SELECT * FROM @p@t ORDER BY name",
			'add'		=> "INSERT INTO @p@t SET @a",
			'delete'	=> "DELETE FROM @p@t @W",
			'update'	=> "UPDATE @p@t SET @a @W"
		);
	}

	/**
	 * Возвращает словарь по его имени
	 * @param $name String: Имя словаря
	 * @retval Dictionary Словарь
	 */
	public function __get($name)
	{
		$name = strtolower($name);

		if (isset($this->dicts_cache[$name]))
			return $this->dicts_cache[$name];

		$dictionary = $this->objects->Cache->get($name, self::CacheClass);
		if ($dictionary !== false)
		{
			$this->dicts_cache[$name] = &$dictionary;
			return $dictionary;
		}

		$dictionary = new Dictionary($this);

		$result = parent::__get("DB")->load_all($name);

		while (($row = $result->fetch()))
			$dictionary->add($row);

		$this->objects->Cache->set($name, self::CacheClass, $dictionary);

		$this->dicts_cache[$name] = &$dictionary;

		return $dictionary;
	}

	/**
	 * Добавляет Данные в словарь
	 * @param $name String: Имя словаря
	 * @param $data Array: Данные для добавления
	 * @retval Integer Идентификатор данных
	 */
	public function add($name, $data)
	{
		$name = strtolower($name);

		parent::__get("DB")->add($name, $data);

		$new_id = parent::__get("DB")->insert_id;

		$this->clear_cache($name);

		return $new_id;
	}

	/**
	 * Удаляет данные из словаря
	 * @param $name String: Имя словаря
	 * @param $data Array: Данные для удаления
	 */
	public function delete($name, $data)
	{
		$name = strtolower($name);

		parent::__get("DB")->delete($name, $data);

		$this->clear_cache($name);
	}

	/**
	 * Обновляет данные в словаре
	 * @param $name String: Имя словаря
	 * @param $data Array: Данные для
	 */
	public function update($name, $new_data, $condition = array())
	{
		$name = strtolower($name);

		parent::__get("DB")->update($name, $new_data, $condition);

		$this->clear_cache($name);
	}

	private function clear_cache($name)
	{
		$this->objects->Cache->delete($name, self::CacheClass);
		unset($this->dicts_cache[$name]);
	}
}

/**
 * Предоставляет функции работы с конкретным словарём
 *
 * @version 1.0.1
 */
class Dictionary
{
	private $tree = array();
	private $rows = array();

	public function get_id($value)
	{
		$values = trim($value);
		if (empty($value))
			return false;

		$id = $this->get($value);
		if ($id === false)
			$id = $this->Dictionaries->add(array('name' => $value));

		if (!is_array($id))
			return $id;

		while (list($key, $value) = each($id))
			$id1 = $value;
		$id = $id1;
	}


	/**
	 * Возвращает значение по цепи ключей в словаре
	 * @param $keys Array: Цепь ключей
	 * @retval Mixed Значение
	 */
	public function get($keys = array())
	{
		return $this->get_from($this->tree, $keys);
	}

	/**
	 * Возвращает значение из словаря по идентификатору
	 * @param $id Integer: Идентификатор элемента
	 * @param $key String: Ключ в ассоциативном массиве значения
	 * @retval Array Значение, если $key не задан
	 * @retval Mixed Элемент значения по ключу, если задан $key
	 * @retval false Если элемент с идентификатором $id отсутствует в словаре, либо в найденном элементе нет ключа $key
	 */
	public function get_by_id($id, $key = false)
	{
		$result = $this->get_from($this->rows, $id);
		if ($key === false)
			return $result;

		if (!isset($result[$key]))
			return false;

		return $result[$key];
	}

	/**
	 * Добавляет значение в словрь
	 * @param $row Array: Значение
	 */
	public function add($row)
	{
		if (!isset($row['id']))
			die("Can't add row without id");

		$id = $row['id'];
		unset($row['id']);

		$this->rows[$id] = $row;

		$cols = count($row);

		$current = &$this->tree;
		while (list(, $value) = each($row))
		{
			$cols--;
			if ($cols == 0)
			{
				$current[$value] = $id;
				break;
			}

			if (!isset($current[$value]))
				$current[$value] = array();

			$current = &$current[$value];
		}
	}

	private function get_from(&$storage, $keys)
	{
		if (!is_array($keys))
			$keys = array($keys);

		$current = &$storage;
		while (list(, $value) = each($keys))
		{
			if (!isset($current[$value]))
				return false;

			$current = &$current[$value];
		}

		return $current;
	}
}

?>