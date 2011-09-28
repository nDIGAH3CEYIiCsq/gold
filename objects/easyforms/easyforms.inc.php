<?php

/**
 * Предоставляет функции обработки веб-форм
 *
 * @uses ObjectErrors
 * @uses ObjectTemplates
 *
 * @version 1.0.2
 */
class ObjectEasyForms extends Object
{
	/**
	 * Возвращает значения полей формы
	 * @param $fields Array: Описание всех требуемых полей<br />
	 *	Используемые элементы:<br />
	 *	type	- Тип передачи значения<br />
	 *	filter	- Фильтр значения<br />
	 *	flags	- Флаги фильтра<br />
	 *	options	- Опции<br />
	 *	require	- Флаг обязательного наличия этого поля<br />
	 *	array	- Флаг представления значения этого поля в виде массива
	 * @param $global_params Array: Глобальные параметры для всех полей
	 * @retval Array Значения требуемых полей
	 * @retval false Если не все поля найдены, либо пустые
	 */
	public function fields($fields, $global_params = array())
	{
		$values = array();

		$errors = false;

		reset($fields);
		while (list($name, $params) = each($fields))
		{
			$params = array_merge($global_params, $params);

			if (!isset($params['type']))
				$params['type'] = INPUT_POST;
			if (!isset($params['filter']))
				$params['filter'] = FILTER_DEFAULT;
			if (!isset($params['flags']))
				$params['flags'] = FILTER_FLAG_NONE;
			if (!isset($params['options']))
				$params['options'] = array();
			if (!isset($params['require']))
				$params['require'] = true;
			if (!isset($params['array']))
				$params['array'] = false;

			$params['flags'] &= ~(FILTER_REQUIRE_ARRAY | FILTER_REQUIRE_SCALAR);

			if ($params['array'] === true)
				$result = $this->get_array($name, $params, $values);
			else
				$result = $this->get_simple($name, $params, $values);

			if (!$result)
				$errors = true;
		}

		if (!$errors)
			return $values;

		$this->bind($values);

		return false;
	}

	/**
	 * Получает значение одного поля формы
	 * @param $name String: Имя поля
	 * @param $type Integer: Хранилище для проверки
	 * @retval Mixed Значение поля формы
	 * @retval false Если поле не найдено или пустое
	 */
	public function field($name, $type = INPUT_POST)
	{
		$fields = array(
			$name => array('type' => $type, 'require' => false),
		);

		$fields = $this->fields($fields);
		if (empty($fields[$name]))
			return false;

		return $fields[$name];
	}

	/**
	 * Устанавливает параметры в шаблонах для значений полей формы
	 * @param $values Array: Значения полей формы
	 */
	public function bind($values)
	{
		reset($values);
		while (list($name, $value) = each($values))
		{
			$name = "field_".$name;

			if (!is_array($value))
				$value = htmlspecialchars($value);
			else
			{
				$binding = array();

				if (!empty($value))
				{
					reset($value);
					while (list($key, $data) = each($value))
						$binding[$key] = htmlspecialchars($data);

					$value = '"'.implode('", "', $binding).'"';
				}
				else
					$value = "";
			}

			$this->Templates->$name = $value;
		}
	}

	private function get_array($name, $params, &$values)
	{
		$options = array(
			'flags'		=> $params['flags'] | FILTER_REQUIRE_ARRAY,
			'options'	=> $params['options']
		);

		$array = filter_input($params['type'], $name, $params['filter'], $options);
		if ($array === null || $array === false)
		{
			$values[$name] = array();
			return true;
		}

		$values[$name] = $array;

		if (!$params['require'])
			return true;

		$ids = false;
		if (isset($params['ids']))
			$ids = filter_input($params['type'], $params['ids'], FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);

		$success = true;

		reset($array);
		while (list($i, $value) = each($array))
		{
			if ($value !== "")
				continue;

			$field_id = $name;
			$field_caption = $name;

			if (isset($params['caption']))
				$field_caption = $params['caption'];

			if (isset($ids[$i]))
			{
				$field_id .= "_".$ids[$i];
				$field_caption .= " ".($i + 1);
			}

			$this->Errors->add("require_field", array($field_id => $field_caption));

			$success = false;
		}

		return $success;
	}

	private function get_simple($name, $params, &$values)
	{
		$options = array(
			'flags'		=> $params['flags'] | FILTER_REQUIRE_SCALAR,
			'options'	=> $params['options']
		);

		$value = filter_input($params['type'], $name, $params['filter'], $options);
		if ($value !== null && $value !== false && $value !== "")
		{
			$values[$name] = $value;
			return true;
		}

		if (!$params['require'])
		{
			$values[$name] = "";
			return true;
		}

		if (isset($params['id']))
			$name = $params['id'];

		if (isset($params['caption']))
			$this->Errors->add("require_field", array($name => $params['caption']));
		else
			$this->Errors->add("require_field", $name);

		return false;
	}
}

?>