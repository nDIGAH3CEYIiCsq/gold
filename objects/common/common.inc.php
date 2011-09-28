<?php

/**
 * Предоставляет функции общие для всей системы
 *
 * @version 1.0.2
 */
class ObjectCommon extends Object
{
	/**
	 * Генерирует пароль заданной длинны
	 * @param $count Integer: Требуемая длинна пароля
	 * @param $use_nums Boolean: Определяет, использовать ли цифры при генерации
	 * @retval String Сгенерированный пароль
	 */
	public function gen_password($count, $use_nums = true)
	{
		$symbols	= array("q", "w", "e", "r", "t", "y", "u", "i", "o", "p", "a", "s", "d", "f", "g", "h", "j", "k", "l", "z", "x", "c", "v", "b", "n", "m");
		$nums		= array("1", "2", "3", "4", "5", "6", "7", "8", "9");

		if ($use_nums)
			$symbols = array_merge($symbols, $nums);

		$symbols_count = count($symbols);

		$result = "";
		while ($count != 0)
		{
			$symbol = $symbols[mt_rand(0, $symbols_count - 1)];
			if (mt_rand(0, 3) == 1)
				$symbol = ucfirst($symbol);

			if (strpos($result, $symbol) !== false)
				continue;

			$result = $result.$symbol;
			$count--;
		}

		return $result;
	}

	/**
	 * Генерирует код подверждения
	 * @retval String Код подтверждения
	 */
	public function gen_confirm_code()
	{
		return strtoupper(sha1(mt_rand()));
	}

	/**
	 * Собирает параметры GET запроса в URL
	 * @param $params Array: Массив параметров
	 * @retval String Собранный URL
	 */
	public function format_params($params)
	{
		$result = "";

		reset($params);
		while (list($key, $value) = each($params))
		{
			if ($result != "")
				$result .= "&amp;";

			$result .= $key."=".$value;
		}

		return $result;
	}

	/**
	 * Копирует непустые элементы из массива согласно списка
	 * @param $data Array: Исходный массив
	 * @param $fields Array: Список копируемых элементов
	 * @retval Array Собранный URL
	 */
	public function copy_fields($data, $fields)
	{
		$result = array();

		while (list(, $field) = each($fields))
		{
			if (!isset($data[$field]))
				continue;
			$result[$field] = $data[$field];
		}

		return $result;
	}

	/**
	 * Перемещает элементы из исходного массива согласно списка
	 * @param[in,out] $data Array: Исходный массив
	 * @param $fields Array: Список перемещаемых элементов
	 * @retval Array Перемещённые элементы
	 */
	public function move_fields(&$data, $fields)
	{
		$result = array();

		while (list(, $field) = each($fields))
		{
			$result[$field] = $data[$field];
			unset($data[$field]);
		}

		return $result;
	}

	/**
	 * Удаляет элементы из исходного массива согласно списка
	 * @param[in,out] $data Array: Исходный массив
	 * @param $fields Array: Список удаляемых элементов
	 */
	public function remove_fields(&$data, $fields)
	{
		while (list(, $field) = each($fields))
			unset($data[$field]);
	}

	/**
	 * Удаляет из массива пустые элементы
	 * @param[in,out] $fields Array: Массив для обработки
	 */
	public function remove_empty(&$fields)
	{
		while (list($key, $value) = each($fields))
		{
			if (!empty($value))
				continue;
			unset($fields[$key]);
		}
	}

	/**
	 * Перемещает элементы из исходного массива и возвращает сериализованный результат
	 * @param[in,out] $data Array: Исходный массив
	 * @param $fields Array: Список перемещаемых элементов
	 * @retval String Сериализованный результат
	 */
	public function serialize_fields(&$data, $fields)
	{
		$result = $this->move_fields($data, $fields);
		$result = serialize($result);

		return $result;
	}

	/**
	 * Выполняет обрезание начальных и конечных пробелов всех элементов массива
	 * @param $data Array: Исходный массив
	 * @retval Array Результирующий массив
	 */
	public function trim($data)
	{
		if (!is_array($data))
			return trim($data);

		while (list($key, $value) = each($data))
			$data[$key] = $this->trim($value);

		return $data;
	}

	/**
	 * Возвращает строку, обрезанную до заданной длинны
	 * @param $text String: Строка
	 * @param $max_len Integer: Максимальная длинна
	 * @retval String Обрезанная строка
	 */
	public function cut($text, $max_len)
	{
		$text = strip_tags($text);

		if (mb_strlen($text) <= $max_len)
			return $text;

		return mb_substr($text, 0, $max_len)."...";
	}

	/**
	 * Возвращает текущий IP пользователя в виде строки
	 * @retval String Текущий IP пользователя
	 * @retval "" Если IP адрес определить не удалось
	 */
	public function get_ip_string()
	{
		$ip = ip2long($_SERVER['REMOTE_ADDR']);
		if ($ip !== false)
			return $_SERVER['REMOTE_ADDR'];

		$ip = ip2long($_SERVER['HTTP_X_FORWARDED_FOR']);
		if ($ip !== false)
			return $_SERVER['HTTP_X_FORWARDED_FOR'];

		return "";
	}

	/**
	 * Возвращает текущий IP пользователя в виде числа
	 * @retval Integer Текущий IP пользователя
	 * @retval false Если IP адрес определить не удалось
	 */
	public function get_ip_long()
	{
		return ip2long($this->get_ip_string());
	}

	/**
	 * Делает первую букву строки в многобайтовой кодировке Заглавной
	 * @param $text String: Строка
	 * @retval String Строка с Заглавной первой буквой
	 */
	public function mb_ucfirst($text)
	{
		$text_length = mb_strlen($text);
		$first_letter = mb_substr($text, 0, 1);
		$first_letter = mb_strtoupper($first_letter);
		$last_letters = mb_substr($text, 1, $text_length - 1);

		return $first_letter.$last_letters;
	}

	/**
	 * Вставляет в исходную строку разделитель через некоторые промежутки
	 * @param $str String: Исходная строка
	 * @param $width Integer: Максимальная длинна элемента
	 * @param $break String: Разделитель
	 * @retval String Результирующая строка
	 */
	public function mb_wordwrap($str, $width, $break = " ")
	{
		$pieces = explode(" ", $str);

		$result = array();
		foreach ($pieces as $piece)
		{
			$current = $piece;
			while (mb_strlen($current) > $width)
			{
				$result[] = mb_substr($current, 0, $width);
				$current = mb_substr($current, $width);
			}
			$result[] = $current;
		}

		return implode($break, $result);
	}

	/**
	 * Форматирует число в формат размера файла
	 * @param[in,out] $size Integer: Размер
	 */
	public function format_size(&$size)
	{
		if (!is_numeric($size))
			return;

		$base = 1000;
		$ext = "Байт";
		if ($size > $base)
		{
			$size /= 1024;
			$ext = "КБ";
		}

		if ($size > $base)
		{
			$size /= 1024;
			$ext = "МБ";
		}

		if ($size > $base)
		{
			$size /= 1024;
			$ext = "ГБ";
		}

		$size = round($size, 2);
		$size .= " ".$ext;
	}

	/**
	 * Проверяет значение на корректность и сбрасывает его на начальное, в случае некорректности
	 * @param[in,out] $value Mixed: Проверяемое значение
	 * @param $options Array: Массив корректных значений
	 */
	public function check_in(&$value, $options)
	{
		if (array_search($value, $options) !== false)
			return;

		$value = $options[0];
	}

	/**
	 * Применяет к значениям массива шаблоны, находящиеся по тем же индексам в массиве шаблонов, что и сами элементы
	 * @param[in,out] $data Array: Индексированный массив для обработки
	 * @param $templates Array: Индексированный массив шаблонов
	 */
	public function apply_templates(&$data, $templates)
	{
		while (list($i, ) = each($data))
		{
			if (empty($templates[$i]))
				continue;

			$templates[$i]->clear();

			$value = $data[$i];
			$templates[$i]->data = $value;
			$templates[$i]->$value = true;
			$data[$i] = (string) $templates[$i];
		}
	}

	/**
	 * Генерирует выдачу клиенту на основе результата запроса к БД
	 * @param $result DatabaseResult: Результат запроса к БД
	 * @param $template TemplateParams: Шаблон выдачи
	 * @param $callback Array: Callback функция предварительной обработки
	 * @retval String Выдача
	 */
	public function format_results($result, $template, $callback = false)
	{
		$content = "";

		while (($row = $result->fetch()))
		{
			if ($callback !== false)
				call_user_func_array($callback, array(&$row));

			$this->clean_to_bind($row);

			$template->bind_params($row);

			$content .= (string) $template;
		}

		return $content;
	}

	/**
	 * Делает первую букву слова в верхнем регистре
	 * @param String $string
	 * @return String
	 */
	public function ucmyFirst($string)
	{
		$string=utf8_decode($string);
		$string=ucfirst($string);
		$string=utf8_encode($string);
		return $string;
	}

	private function clean_to_bind(&$data)
	{
		reset($data);
		while (list($key, $value) = each($data))
		{
			if (is_array($value))
			{
				unset($data[$key]);
				continue;
			}

			$value = strip_tags($value);
			$value = str_replace("&amp;", "&", $value);
			$value = str_replace("&", "&#038;", $value);
			$value = str_replace("'", "&#039;", $value);
			$value = str_replace("\"", "&#034;", $value);
			$value = str_replace("\\", "&#092;", $value);

			$data[$key] = $value;
		}
	}
}

?>