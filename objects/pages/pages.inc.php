<?php

/**
 * Предоставляет функции работы со страницами в БД
 *
 * @uses DatabaseInterface
 * @uses ObjectCache
 * @uses ObjectLog
 * @uses Parsable
 *
 * @version 1.0.3
 */
class ObjectPages extends Object implements DatabaseInterface
{
	const CacheClass = "templates";

	private $cache = array();

	static public function get_queries()
	{
		return array(
			'get'			=> "SELECT * FROM @ppages WHERE name = BINARY @s",
			'get_all'		=> "SELECT * FROM @ppages WHERE name LIKE BINARY @s",
			'get_in'		=> "SELECT * FROM @ppages WHERE name IN (BINARY @l)",
			'add'			=> "INSERT INTO @ppages SET @a",
			'replace'		=> "REPLACE @ppages SET @a",
			'update'		=> "UPDATE @ppages SET @a WHERE name = BINARY @s",
			'delete'		=> "DELETE FROM @ppages WHERE name IN (BINARY @l)",
			'get_names'		=> "SELECT name FROM @ppages ORDER BY name ASC"
		);
	}

	/**
	 * Возвращает данные страницы
	 * @param $name String: Имя страницы
	 * @retval Array Данные страницы
	 */
	public function get($name)
	{
		$data = $this->get_template($name);
		if ($data === false)
			$this->Log->error("Page {$name} doesn't exists");

		return $data;
	}

	/**
	 * Возвращает данные страницы без парсинга шаблона
	 * @param $name String: Имя страницы
	 * @retval Array Данные страницы
	 */
	public function get_raw($name)
	{
		$data = $this->get_template_raw($name);
		if ($data === false)
			$this->Log->error("Page {$name} doesn't exists");

		return $data;
	}

	/**
	 * Возвращает данные страниц по префиксу имени
	 * @param $prefix String: Префикс имени
	 * @retval Array Данные страниц
	 */
	public function get_all($prefix)
	{
		if (is_array($prefix))
			return $this->DB->get_in($prefix);
		return $this->DB->get_all($prefix."%");
	}

	/**
	 * Добавляет страницу
	 * @param $data Array: Данные страницы
	 */
	public function add($data)
	{
		$this->invalidate($data['name']);
		$this->unparse($data);
		$this->DB->add($data);
	}

	/**
	 * Заменяет данные страницы
	 * @param $data Array: Данные страницы
	 */
	public function replace($data)
	{
		$this->invalidate($data['name']);
		$this->unparse($data);
		$this->DB->replace($data);
	}

	/**
	 * Удаляет страницу по имени или массиву имён
	 * @param $names Mixed: Имя страницы для удаления или массив имён
	 */
	public function delete($names)
	{
		if (!is_array($names))
			$names = array($names);

		while (list(, $name) = each($names))
			$this->invalidate($name);

		$this->DB->delete($names);
	}

	/**
	 * Обновляет данные страницы
	 * @param $data Array: Данные страницы
	 * @param $name String: Старое имя страницы
	 */
	public function update($data, $name = null)
	{
		if ($name === null)
			$name = $data['name'];
		else
			$this->invalidate($data['name']);

		$this->invalidate($name);
		$this->unparse($data);
		$this->DB->update($data, $name);
	}

	/**
	 * Переименовывает страницу
	 * @param $old_name String: Старое имя
	 * @param $new_name String: Новое имя
	 */
	public function rename($old_name, $new_name)
	{
		if ($old_name == $new_name)
			return;

		$this->invalidate($old_name);
		$this->invalidate($new_name);

		$this->DB->update(array('name' => $new_name), $old_name);
	}

	/**
	 * Проверяет существование страницы
	 * @param $name String: Имя страницы
	 * @retval true Страница существует
	 * @retval false Страница не существует
	 */
	public function exist($name)
	{
		return ($this->get_template($name) !== false);
	}

	/**
	 * Проверяет существование страницы без парсинга шаблона
	 * @param $name String: Имя страницы
	 * @retval true Страница существует
	 * @retval false Страница не существует
	 */
	public function exist_raw($name)
	{
		return ($this->get_template_raw($name) !== false);
	}

	/**
	 * Удаляет страницу из кэша
	 * @param $name String: Имя страницы
	 */
	public function invalidate($name)
	{
		$cache_key = $this->get_cache_key($name);

		unset($this->cache[$name]);
		$this->Cache->delete($cache_key, self::CacheClass);
	}

	/**
	 * Возвращает список имён страниц
	 * @retval Array Список имён
	 */
	public function get_names()
	{
		$names = $this->Cache->get("names", self::CacheClass);
		if ($names !== false)
			return $names;

		$result = $this->DB->get_names();

		$names = array();
		while (($row = $result->fetch()))
			array_push($names, $row['name']);

		$this->Cache->set("names", self::CacheClass, $names);

		return $names;
	}

	/**
	 * Обновляет список имён страниц
	 * @param $added_pages Array: Добавляемые страницы
	 * @param $deleted_pages Array: Удаляемые страницы
	 */
	public function update_names($added_pages, $deleted_pages)
	{
		$names = $this->Cache->get("names", self::CacheClass);
		if ($names === false)
			return;

		$names = array_diff($names, $deleted_pages);
		$names = array_merge($names, $added_pages);
		$names = array_unique($names);

		sort($names);

		$this->Cache->set("names", self::CacheClass, $names);
	}

	/**
	 * Выполняет парсинг данных страницы
	 * @param $data Array: Данные страницы в исходном виде
	 */
	public function parse(&$data)
	{
		if (!empty($data['params']))
			$data['params'] = unserialize($data['params']);
		else
			$data['params'] = array();
	}

	private function get_template($name)
	{
		if (isset($this->cache[$name]))
			return $this->cache[$name];

		$cache_key = $this->get_cache_key($name);

		$data = $this->Cache->get($cache_key, self::CacheClass);
		if ($data !== false)
		{
			if ($data === null)
				$data = false;

			$this->cache[$name] = $data;
			return $data;
		}

		$result = $this->DB->get($name);
		if (!$result->is_empty())
			$data = $result->fetch();

		if ($data !== false)
		{
			$this->extract($data);
			$this->cache[$name] = $data;
		}
		else
		{
			$data = null;
			$this->cache[$name] = false;
		}

		$this->Cache->set($cache_key, self::CacheClass, $data);

		if ($data === null)
			return false;
		return $data;
	}

	private function get_template_raw($name)
	{
		$result = $this->DB->get($name);
		if ($result->is_empty())
			return false;

		$data = $result->fetch();

		$this->parse($data);

		return $data;
	}

	private function extract(&$data)
	{
		$this->parse($data);
		$this->pack($data);
	}

	private function unparse(&$data)
	{
		$data['content'] = str_replace("\r", "", $data['content']);

		$params = &$data['params'];
		while (list($name, $content) = each($params))
			$params[$name] = str_replace("\r", "", $content);

		if (!empty($data['params']))
			$data['params'] = serialize($data['params']);
		else
			$data['params'] = "";
	}

	private function pack(&$data)
	{
		$data['content'] = new Parsable($data['content']);
		$this->apply_includes($data['content']);

		$params = &$data['params'];
		while (list($name, $content) = each($params))
		{
			$params[$name] = new Parsable($content);
			$this->apply_includes($params[$name]);
		}
	}

	private function get_cache_key($name)
	{
		return "page_".$name;
	}

	private function apply_includes(&$parsable)
	{
		$data = $parsable->get_data();

		$cnt = count($data);
		for ($i = 0; $i < $cnt; $i++)
		{
			$param = $data[$i];

			switch ($param['type'])
			{
				case "include":
					$value = $this->get($param['value']);
					$value_data = $value['content']->get_data();

					array_splice($data, $i, 1, $value_data);

					$new_cnt = count($value_data) - 1;

					$cnt += $new_cnt;
					$i += $new_cnt;
					break;
				case "param":
					if (!isset($param['options']))
						break;

					$this->parse_options($data[$i]['options']);
					break;
			}
		}

		$parsable->set_data($data);
	}

	private function parse_options(&$options)
	{
		reset($options);
		while (list($name, $data) = each($options))
		{
			if ($data === true)
				continue;

			$this->apply_includes($options[$name]);
		}
	}
}

?>