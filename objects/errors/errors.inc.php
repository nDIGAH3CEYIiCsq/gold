<?php

/**
 * Предоставляет функции уведомления пользователя об ошибках
 *
 * @uses ErrorsTree
 * @uses ObjectTemplates
 *
 * @version 1.0.2
 */
class ObjectErrors extends Object
{
	private $errors;

	public function __construct(&$objects)
	{
		parent::__construct($objects);

		$this->errors = new ErrorsTree();
	}

	/**
	 * Устанавливает параметры, необходимые клиентскому модулю для отображения уведомлений об ошибках
	 */
	public function handler_bind()
	{
		if ($this->is_empty())
			return;

		$bindings = $this->get_bindings();

		$this->Templates->add("/Объекты/Errors");
		$this->Templates->bind_params(array('errors' => $bindings));
	}

	/**
	 * Возвращает данные, отправляемые браузеру
	 * @retval String Данные
	 */
	public function get_content()
	{
		if ($this->is_empty())
			return "";

		$bindings = $this->get_bindings();

		$template = $this->Templates->get("/Объекты/Errors");
		$template->bind_params(array('errors' => $bindings));

		return (string) $template;
	}

	/**
	 * Возвращает список уведомлений об ошибках заданного типа
	 * @param $types Array: Цепь типа уведомлений
	 * @retval Array Список уведомлений заданного типа
	 */
	public function get($types)
	{
		return $this->errors->get($types);
	}

	/**
	 * Добавляет новое уведомление о возникшей ошибке
	 * @param $types Array: Цепь типа уведомлений
	 * @param $params Array: Параметры уведомления
	 */
	public function add($types, $params)
	{
		$this->errors->add($types, $params);
	}

	/**
	 * Очищает список уведомлений об ошибках
	 */
	public function clear()
	{
		$this->errors->clear();
	}

	/**
	 * Проверяет наличие уведомлений об ошибоках
	 * @param $types Array: Цепь типа уведомлений
	 * @retval true Уведомлений об ошибках нет
	 * @retval false Уведомления об ошибках есть
	 */
	public function is_empty($types = array())
	{
		return $this->errors->is_empty($types);
	}

	private function get_bindings()
	{
		$errors = $this->errors->get_all();

		$bindings = array();
		while (list($type, $params) = each($errors))
		{
			$data = "";

			reset($params);
			while (list(, $param) = each($params))
			{
				if ($data != "")
					$data .= ", ";

				if (!is_array($param))
				{
					$data .= "'".$param."'";
					continue;
				}

				$list = "";

				reset($param);
				while (list($key, $value) = each($param))
				{
					if ($list != "")
						$list .= ", ";
					$list .= "'".$key."': '".$value."'";
				}

				$data .= "{".$list."}";
			}

			array_push($bindings, "'".$type."': new Array(".$data.")");
		}

		$bindings = implode(", ", $bindings);

		return $bindings;
	}
}

/**
 * Предоставляет функции работы с деревом ошибок
 *
 * @version 1.0.1
 */
class ErrorsTree
{
	private $errors = array();
	private $children = array();

	public function get($types)
	{
		return $this->get_last($types);
	}

	public function add($types, $params)
	{
		$last = &$this->add_last($types);
		array_push($last->errors, $params);
	}

	public function clear()
	{
		$this->errors = array();
		$this->children = array();
	}

	public function get_all()
	{
		$errors = array();

		reset($this->children);
		while (list($type, $cur) = each($this->children))
			$errors[$type] = $cur->get_errors();

		return $errors;
	}

	public function get_errors()
	{
		$errors = array();
		$errors = array_merge($errors, $this->errors);

		reset($this->children);
		while (list(, $cur) = each($this->children))
			$errors = array_merge($errors, $cur->get_errors());

		return $errors;
	}

	public function is_empty($types = array())
	{
		$cur = $this->get_last($types);
		if ($cur === false)
			return true;

		if (!empty($cur->errors))
			return false;
		if (!empty($cur->children))
			return false;

		return true;
	}

	private function get_last($types)
	{
		if (!is_array($types))
			$types = array($types);

		$cur = &$this;
		while (list(, $type) = each($types))
		{
			if (!isset($cur->children[$type]))
				return false;
			$cur = &$cur->children[$type];
		}

		return $cur;
	}

	private function &add_last($types)
	{
		if (!is_array($types))
			$types = array($types);

		$cur = &$this;
		while (list(, $type) = each($types))
		{
			if (!isset($cur->children[$type]))
				$cur->children[$type] = new ErrorsTree();
			$cur = &$cur->children[$type];
		}

		return $cur;
	}
}

?>