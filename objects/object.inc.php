<?php

/**
 * Предоставляет функции быстрого доступа к объектам
 *
 * @version 1.0.2
 */
abstract class Object
{
	protected $objects;
	protected $properties = array();

	/**
	 * Конструктор класса
	 * @param $objects Objects: Коллекция объектов
	 */
	public function __construct(&$objects)
	{
		$this->objects = &$objects;
		$this->load_connectors();
	}

	/**
	 * Возвращает экземпляр объекта по имени
	 * @param $name String: Имя объекта
	 * @retval Object Экземпляр объекта
	 */
	public function __get($name)
	{
		if (isset($this->properties[$name]))
			return $this->properties[$name];
		return $this->objects->$name;
	}

	/**
	 * Устанавливает значение свойства
	 * @param $name String: Имя свойства
	 * @param $value Mixed: Значение свойства
	 */
	public function set_property($name, $value)
	{
		$this->properties[$name] = $value;
	}

	private function load_connectors()
	{
		$interfaces = class_implements($this, false);

		while (list(, $interface) = each($interfaces))
		{
			if (!preg_match("/^([a-zA-Z0-9+]+)Interface$/", $interface, $matches))
				continue;

			call_user_func_array(array($matches[1]."Connector", "init"), array(&$this));
		}
	}
}

/**
 * Реализует базовую функциональность коллекций
 *
 * @version 1.0.1
 */
abstract class Layer
{
	protected $objects = array();

	/**
	 * Возвращает экземпляр объекта по его имени
	 * @param $name String: Имя объекта
	 * @retval Object Экземпляр объекта
	 * @see Layer::get
	 */
	public function &__get($name)
	{
		return $this->get($name);
	}

	/**
	 * Возвращает экземпляр объекта по его имени
	 * @param $name String: Имя объекта
	 * @retval Object Экземпляр объекта
	 */
	public function &get($name)
	{
		if (isset($this->objects[$name]))
			return $this->objects[$name];

		$this->load($name);

		if (isset($this->objects[$name]))
			return $this->objects[$name];

		die("Can't find object {$name}");
	}

	/**
	 * Сохраняет экземпляр объекта в кэше
	 * @param $name String: Имя объёекта
	 * @param $object Object: Экземпляр объекта
	 */
	protected function set($name, &$object)
	{
		$this->objects[$name] = $object;
	}

	/**
	 * Загружает исходный код объекта и создаёт его экземпляр
	 * @param $name String: Имя объекта
	 */
	abstract protected function load($name);
}

/**
 * Реализует коллекцию объектов
 *
 * @uses Layer
 *
 * @version 1.0.1
 */
class Objects extends Layer
{
	/**
	 * Вызывает указанный метод для всех загруженных в коллекцию объектов
	 * @param $method String: Имя метода
	 * @param $args Array: Аргументы метода
	 */
	public function call($method, $args = array())
	{
		reset($this->objects);
		while (list(, $object) = each($this->objects))
		{
			if (!method_exists($object, $method))
				continue;

			call_user_func_array(array(&$object, $method), $args);
		}
	}

	protected function load($name)
	{
		$class_name = "Object".$name;

		$instance = new $class_name($this);

		$this->set($name, $instance);
	}
}

/**
 * Реализует парсер шаблонов
 *
 * @version 1.0.1
 */
class Parsable
{
	private $data;

	/**
	 * Конструктор класса
	 * @param $content String: Данные шаблона
	 * @param $parsable Boolean: Определяет, требуется ли парсинг шаблона
	 */
	public function __construct($content = "", $parsable = true)
	{
		$this->data = $this->parse($content, $parsable);
	}

	/**
	 * Устанавливает данные шаблона
	 * @param $data String: Данные шаблона
	 */
	public function set_data($data)
	{
		$this->data = $data;
	}

	/**
	 * Возвращает данные шаблона
	 * @retval String Данные шаблона
	 */
	public function get_data()
	{
		return $this->data;
	}

	/**
	 * Определяет пустой ли шаблон или нет
	 * @retval true Шаблон пустой
	 * @retval false Шаблон не пустой
	 */
	public function is_empty()
	{
		if (empty($this->data))
			return true;

		if (empty($this->data[0]))
			return true;

		return false;
	}

	/**
	 * Присоединяет данные шаблона к текущему шаблону
	 * @param $content String: Данные шаблона
	 */
	public function add(&$content)
	{
		$this->data = array_merge($this->data, $content->data);
	}

	private function parse(&$content, $parsable)
	{
		$len = strlen($content);
		if (!$len)
			return array();

		if (!$parsable)
			return array($content);

		$data = array();

		$open_brackets = array();
		$last_bracket_type = null;
		$last_content_pos = 0;

		for ($i = 0; $i < $len; $i++)
		{
			switch ($content[$i])
			{
				case '\\':
				{
					$i++;
					break;
				}
				case '{':
				{
					if ($i == $len - 1)
						die("Unexpected open bracket at {$i}");

					if ($last_bracket_type === null && $i != $last_content_pos)
						$data[] = $this->get_value($content, $last_content_pos, $i);

					switch ($content[$i + 1])
					{
						case '*':
							$open = array('pos' => $i, 'offset' => 2, 'type' => "comment");
							$i++;
							break;
						case '[':
							$open = array('pos' => $i, 'offset' => 2, 'type' => "include");
							$i++;
							break;
						default:
							$open = array('pos' => $i, 'offset' => 1, 'type' => "param");
							break;
					}

					if ($last_bracket_type == "comment" && $open['type'] != "comment")
						break;

					array_push($open_brackets, $open);
					$last_bracket_type = $open['type'];
					break;
				}
				case '}':
				{
					if ($last_bracket_type === null)
						die("Unexpected close bracket");

					$last_content_pos = $i + 1;

					$prev_symbol = $content[$i - 1];
					if ($i >= 2 && $content[$i - 2] == '\\')
						$prev_symbol = "";

					switch ($prev_symbol)
					{
						case '*':
							$close = array('pos' => $i, 'offset' => 2, 'type' => "comment");
							break;
						case ']':
							$close = array('pos' => $i, 'offset' => 2, 'type' => "include");
							break;
						default:
							$close = array('pos' => $i, 'offset' => 1, 'type' => "param");
							break;
					}

					if ($last_bracket_type == "comment" && $close['type'] != "comment")
						break;

					$open = array_pop($open_brackets);
					if ($open['type'] != $close['type'])
						die("No open bracket for close bracket with type {$close['type']}");

					$cnt = count($open_brackets);
					if ($cnt != 0)
						$last_bracket_type = $open_brackets[$cnt - 1]['type'];
					else
						$last_bracket_type = null;

					if ($open['type'] == "comment")
						break;

					if (!empty($open_brackets))
						break;

					$open['length'] = $close['pos'] - $open['pos'] + 1;

					$value = substr($content, $open['pos'] + $open['offset'], $open['length'] - $open['offset'] - $close['offset']);
					$value = array('type' => $open['type'], 'value' => $value);

					if ($open['type'] === "param")
						$this->fill_options($value);

					$data[] = $value;
					break;
				}
			}
		}

		if (!empty($open_brackets))
			die("Open/close brackets mismatch");

		if ($last_content_pos != $len)
			$data[] = $this->get_value($content, $last_content_pos, $len);

		return $data;
 	}

	private function get_value(&$content, $start, $stop)
	{
		$value = substr($content, $start, $stop - $start);
		$value = stripcslashes($value);

		return $value;
	}

	private function fill_options(&$param)
	{
		$pieces = $this->escaped_explode("|", $param['value']);

		$value = array_shift($pieces);
		$value = trim($value);

		$options = array();
		while (list(, $option) = each($pieces))
		{
			$colon_pos = strpos($option, ":");
			if ($colon_pos === false)
			{
				$options[$option] = true;
				continue;
			}

			$option_key = substr($option, 0, $colon_pos);
			$option_key = trim($option_key);

			$option_value = substr($option, $colon_pos + 1);

			$option_value = new Parsable($option_value);

			$options[$option_key] = $option_value;
		}

		$param['value'] = $value;

		if (!empty($options))
			$param['options'] = $options;
	}

	private function escaped_explode($delimeter, $string)
	{
		$values = array();

		$open_brackets = 0;
		$last_pos = 0;

		$len = strlen($string);
		for ($i = 0; $i < $len; $i++)
		{
			switch ($string[$i])
			{
				case '\\':
				{
					$i++;
					break;
				}
				case '{':
				{
					$open_brackets++;
					break;
				}
				case '}':
				{
					$open_brackets--;
					break;
				}
				case $delimeter:
				{
					if ($open_brackets != 0)
						continue;

					if ($i == $last_pos)
						$values[] = "";
					else
						$values[] = substr($string, $last_pos, $i - $last_pos);

					$last_pos = $i + 1;
					break;
				}
			}
		}

		$value = substr($string, $last_pos);
		if ($value !== false)
			$values[] = $value;

		return $values;
	}
}

?>