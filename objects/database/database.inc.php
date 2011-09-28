<?php

/**
 * Предоставляет функции работы с БД
 *
 * @uses DatabaseResult
 *
 * @version 1.0.3
 */
class Database
{
	private $sql = null;

	/**
	 * Прокси-метод для обращения к свойствам соединения
	 * @param $name String: Имя свойства
	 * @retval Mixed Значение свойства
	 */
	public function __get($name)
	{
		return $this->sql->$name;
	}

	/**
	 * Выполняет множественные запросы к БД
	 * @param $queries Array: Запросы
	 * @param $params Array: Параметры запроса
	 * @retval Array Данные результатов запросов
	 */
	public function multi_query($queries, $params)
	{
		$this->connect();

		$query = implode(" ; ", $queries);
		$query = $this->set_named_placeholders($query, $params);

		if (!$this->sql->multi_query($query))
			die("Error occured while executing multi-query: ".$query);

		$data = array();
		while ($this->sql->more_results())
		{
			$this->sql->next_result();

			$result = $this->sql->store_result();
			if (!$result)
				continue;

			$data += $result->fetch_assoc();

			$result->free();
		}

		return $data;
	}

	/**
	 * Выполняет запрос к БД
	 * @param $query String: Запрос
	 * @param $params Array: Параметры запроса
	 * @retval DatabaseResult Результат запроса
	 */
	public function query($query, $params)
	{
		$this->connect();

		$query = $this->set_placeholders($query, $params);

		return $this->execute($query);
	}

	private function get_name($query, $cur_pos)
	{
		$cur_pos += 2;
		$len = strlen($query);

		$name_pos = false;
		for ($i = $cur_pos; $i < $len; $i++)
		{
			if ($query[$i] == "{")
			{
				$name_pos = $i;
				continue;
			}

			if ($query[$i] != "}" || $name_pos === false)
				continue;

			$name = substr($query, $cur_pos, $name_pos - $cur_pos);
			$condition = substr($query, $name_pos + 1, $i - $name_pos - 1);

			$length = $i - $cur_pos + 1;

			return array($name, $condition, $length);
		}

		die("Named placeholder parse error");
	}

	private function set_named_placeholders($query, $params)
	{
		$param = $this->next_param($params, true, false);

		$cur_pos = 0;
		while (true)
		{
			$cur_pos = strpos($query, "@@", $cur_pos);
			if ($cur_pos === false)
				break;

			list($name, $condition, $length) = $this->get_name($query, $cur_pos);

			if (isset($param[$name]))
				$replacement = $this->set_placeholders($condition, array($param[$name]));
			else
				$replacement = "";

			$query = substr_replace($query, $replacement, $cur_pos, $length + 2);
			$cur_pos += strlen($replacement);
		}

		return $this->set_placeholders($query, $params);
	}

	private function set_placeholders($query, $params)
	{
		$cur_pos = 0;

		while (true)
		{
			$cur_pos = strpos($query, "@", $cur_pos);
			if ($cur_pos === false)
				break;

			$replacement = "";
			$length = 2;

			$type = $query[$cur_pos + 1];
			switch ($type)
			{
				case 'p':	// Database prefix
					$replacement = DATABASE_PREFIX;
					break;
				case 't':	// Plain text
					$param = $this->next_param($params, false, true, false);
					$replacement = $param;
					break;
				case 'i':	// Integer
					$param = $this->next_param($params);
					$replacement = intval($param);
					break;
				case 'f':	// Float
					$param = $this->next_param($params);
					$replacement = floatval($param);
					break;
				case 'q':	// Quoted string
					$param = $this->next_param($params);
					$replacement = "'\"".$param."\"'";
					break;
				case 's':	// String
					$param = $this->next_param($params);
					$replacement = "'".$param."'";
					break;
				case 'a':	// Array
					$param = $this->next_param($params, true);
					$replacement = $this->make_pairs($param);
					break;
				case 'l':	// List
					$param = $this->next_param($params, true);
					$replacement = $this->make_list($param);
					break;
				case 'L':	// LIMIT
					$param = $this->next_param($params, null, false);
					if (empty($param))
						break;

					$replacement = "LIMIT ";

					$cnt = count($param);
					if ($cnt == 1)
						$replacement .= $param[0];
					else if ($cnt == 2)
						$replacement .= $param[1].",".$param[0];
					break;
				case 'O':	// ORDER
					$param = $this->next_param($params, null, false);
					if (empty($param))
						break;

					$replacement = "ORDER BY ";

					$cnt = count($param);
					if ($cnt == 1)
						$replacement .= $param[0];
					else if ($cnt == 2)
					{
						if ($param[1] != "ASC" && $param[1] != "DESC")
							$param[1] = "ASC";
						$replacement .= $param[0]." ".$param[1];
					}
					break;
				case 'W':	// WHERE
				case 'R':	// OR
				case 'A':	// AND
					$param = $this->next_param($params, true, false);
					if (empty($param))
						break;

					$condition = " AND ";
					if ($type == "A")
						$replacement = "AND ";
					else if ($type == "R")
					{
						if (strpos($query, "WHERE") === false)
							$replacement = " WHERE ";
						else
							$replacement = " AND ";
						$condition = " OR ";
					}
					else
						$replacement = "WHERE ";

					$replacement .= $this->make_pairs($param, $condition);

					break;
				case '@':
					$space_pos = strpos($query, " ", $cur_pos + 2);
					if ($space_pos === false)
						$space_pos = strlen($query);

					$placeholder = substr($query, $cur_pos + 2, $space_pos - $cur_pos - 2);

					$replacement = $this->parse_long($placeholder, $params);
					$length = $space_pos - $cur_pos;
					break;
				default:
					die("Unknown placeholder @{$type}");
			}

			$query = substr_replace($query, $replacement, $cur_pos, $length);
			$cur_pos += strlen($replacement);
		}

		return $query;
	}

	private function parse_long($placeholder, &$params)
	{
		$options = str_split($placeholder);
		$type = array_shift($options);
		$options = array_flip($options);

		switch ($type)
		{
			case 'W':
			case 'A':
				$param = $this->next_param($params, true, false);
				if (empty($param))
					return "";

				if ($type == "A")
					$replacement = "AND ";
				else
					$replacement = "WHERE ";

				$replacement .= $this->make_pairs($param, " AND ", "LIKE");

				return $replacement;
		}
	}

	private function connect()
	{
		if ($this->sql !== null)
			return;

		$sql = new mysqli(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);
		if ($sql->connect_error)
			die("Can't connect to DB");

		if ($sql->query("SET NAMES utf8") === false)
			die("Can't set encoding");

		$this->sql = $sql;
	}

	private function execute($query)
	{
		$result = $this->sql->query($query);
		if ($result === false)
			die("Error occured while executing query: ".$query);

		return new DatabaseResult($result);
	}

	private function next_param(&$params, $is_array = false, $required = true, $protect = true)
	{
		$param = each($params);
		if ($param === false)
		{
			if (!$required)
				return false;
			die("No param for placeholder");
		}

		$param = $param[1];
		if ($param === null && !$required)
			return $param;

		if ($is_array === null)
		{
			if (!is_array($param))
				$param = array($param);
		}
		else
		{
			if (is_array($param) !== $is_array)
				die("Expected param type mismatch");
		}

		if ($protect)
			$this->protect($param);

		return $param;
	}

	private function protect(&$value)
	{
		if (is_array($value))
		{
			reset($value);
			while (list($name, ) = each($value))
				$this->protect($value[$name]);
			return;
		}

		if (is_string($value))
		{
			$value = $this->sql->real_escape_string($value);
			return;
		}
	}

	private function make_pairs($data, $glue = ", ", $operand = "=")
	{
		$pairs = "";

		reset($data);

		while (list($name, $value) = each($data))
		{
			if (is_array($value) && $glue === " AND ")
			{
				if (!isset($value['values']))
					die("no set values");

				if (!is_array($value['values']))
					$value['values'] = array($value['values']);

				if (!isset($value['operation']))
					$value['operation'] = "=";

				if (!empty($pairs))
					$pairs .= " AND ";

				if ($value['operation'] == "between")
				{
					$pairs .= " (".$name.">=".$value['values'][0]." AND ".$name."<=".$value['values'][1].")";
				}
				else
				{
					$pairs .= " (";

					$local_pairs = "";
					reset($value['values']);
					while(list(, $value2) = each($value['values']))
					{
						if ($local_pairs != "")
							$local_pairs .= " OR ";

						$local_pairs .= $name." ".$value['operation']." '".$value2."'";
					}
					$pairs .= $local_pairs.") ";
				}
				continue;
			}

			if ($pairs != "")
				$pairs .= $glue;

			$pairs .= $name." ".$operand." '".$value."'";
		}

		if ($glue == " OR ")
			$pairs = "( $pairs )";

		return $pairs;
	}

	private function make_list($data)
	{
		$list = implode("', '", $data);
		if ($list != "")
			$list = "'".$list."'";

		return $list;
	}
}

/**
 * Предоставляет функции работы с результатами запроса
 *
 * @version 1.0.1
 */
class DatabaseResult
{
	private $result;

	/**
	 * Конструктор класса
	 * @param $result MySQLi_Result: Результат запроса
	 */
	public function __construct($result)
	{
		$this->result = $result;
	}

	/**
	 * Извлекает данные из результата в виде ассоциативного массива
	 * @param $field String: Ключ требуемого элемента в массиве данных
	 * @retval Array Массив данных, если ключ не задан
	 * @retval Mixed Значение элемента по ключу
	 */
	public function fetch($field = false)
	{
		$row = $this->result->fetch_assoc();
		if ($field === false)
			return $row;

		if (!isset($row[$field]))
			return false;

		return $row[$field];
	}

	/**
	 * Извлекает данные из результата в виде нумерованного массива
	 * @retval Mixed Данные
	 */
	public function fetch_row()
	{
		return $this->result->fetch_row();
	}

	/**
	 * Извлекает количество строк в результате
	 * @retval Integer Количество строк
	 */
	public function num_rows()
	{
		return $this->result->num_rows;
	}

	/**
	 * Проверяет наличие данных в результате
	 * @retval true В результате есть данные
	 * @retval false Данных в результате нет
	 */
	public function is_empty()
	{
		return ($this->num_rows() == 0);
	}
}

/**
 * Коннектор класса Database
 *
 * @uses Database
 *
 * @version 1.0.1
 */
class DatabaseConnector
{
	static private $instance = null;

	private $queries;

	/**
	 * Конструктор класса
	 * @param $queries Array: Поддерживаемые запросы
	 * @see DatabaseInterface::get_queries
	 */
	public function __construct($queries)
	{
		$this->queries = $queries;
	}

	/**
	 * Прокси-метод для обращения к свойствам экземпляра класса Database
	 * @param $name String: Имя свойства
	 * @retval Mixed Значение свойства
	 */
	public function __get($name)
	{
		return self::$instance->$name;
	}

	/**
	 * Прокси-метод для выполнения запроса по имени
	 * @param $query_name String: Имя запроса
	 * @param $params Array: Параметры запроса
	 * @retval DatabaseResult Результат, если запрос одиночный
	 * @retval Array Массив данных, если запрос множественный
	 */
	public function __call($query_name, $params)
	{
		if (!isset($this->queries[$query_name]))
			die("Unknown query name {$query_name}");

		$query = $this->queries[$query_name];

		if (is_array($query))
		{
			$count = count($query);

			if ($count == 0)
				die("Can't run empty query");
			if ($count != 1)
				return self::$instance->multi_query($query, $params);

			$query = $query[0];
		}

		return self::$instance->query($query, $params);
	}

	/**
	 * Заполняет свойства владельца новыми полями для доступа к классу Database
	 * @param $owner Object: Владелец класса
	 */
	static public function init(&$owner)
	{
		if (self::$instance === null)
			self::$instance = new Database();

		$queries = call_user_func(array($owner, "get_queries"));
		$owner->set_property("DB", new DatabaseConnector($queries));
	}
}

/**
 * Интерфейс, который объекты должны реализовывать для использования класса Database
 *
 * @ref DatabaseConnector
 *
 * @uses DatabaseConnector
 */
interface DatabaseInterface
{
	/**
	 * Возвращает список запросов, известных объекту
	 * @retval Array Массив запросов
	 *
	 * Пример возвращаемого значения:
	 * @code
	 * array(
	 *	'query_1'	=> "SELECT * FROM table WHERE id = @i",
	 *	'multi_query_1'	=> array(
	 *				"SELECT * FROM table @@name{WHERE name LIKE @s}",
	 *				"SELECT pos FROM table2 @@id{WHERE id = @i}"
	 *			)
	 * );
	 * @endcode
	 */
	static function get_queries();
}

?>