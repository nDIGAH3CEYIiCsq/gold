<?php

/**
 * Представляет универсальный набор параметров
 *
 * @uses Parsable
 * @uses TemplateParam
 *
 * @version 1.0.2
 */
abstract class TemplateParams extends Object
{
	protected $params = array();

	/**
	 * Устанавливает параметр
	 * @param $name String: Имя параметра
	 * @param $value String: Значение параметра
	 * @see TemplateParams::set_param
	 */
	public function __set($name, $value)
	{
		$this->set_param($name, $value);
	}

	/**
	 * Возвращает параметр
	 * @param $name String: Имя параметра
	 * @retval TemplateParam Параметр
	 * @see TemplateParams::get_param
	 */
	public function __get($name)
	{
		return $this->get_param($name);
	}

	/**
	 * Возвращает содержимое страницы
	 * @retval String Содержимое страницы
	 */
	public function __toString()
	{
		$content = $this->get_content();
		$content = $this->format_content($content);

		return $content;
	}

	/**
	 * Возвращает параметр
	 * @param $name String: Имя параметра
	 * @retval TemplateParam Параметр
	 */
	public function get_param($name)
	{
		$this->load();

		if (!isset($this->params[$name]))
			return false;

		$param = new TemplateParam($this->params[$name]);
		$param->params = $this->params;

		return $param;
	}

	/**
	 * Устанавливает параметр
	 * @param $name String: Имя параметра
	 * @param $value String: Значение параметра
	 */
	public function set_param($name, $value)
	{
		$this->params[$name] = new Parsable($value, false);
	}

	/**
	 * Возвращает параметры, установлнные в страницах
	 * @param $params Array: Список требуемых параметров
	 * @param $associative Boolean: Определяет, возвращать ли параметры в виде ассоциативного массива
	 * @retval Array Параметры
	 */
	public function get_params($params, $associative = true)
	{
		$result = array();

		while (list(, $name) = each($params))
		{
			$param = $this->get_param($name);
			if ($param === false)
				return false;

			if ($associative)
				$result[$name] = $param;
			else
				array_push($result, $param);
		}

		return $result;
	}

	/**
	 * Устанавливает параметры из массива, добавляя к имени каждого префикс
	 * @param $params Array: Массив параметров
	 * @param $prefix String: Префикс имени для каждого параметра
	 */
	public function bind_params($params, $prefix = "")
	{
		reset($params);
		while (list($name, $value) = each($params))
			$this->set_param($prefix.$name, $value);
	}

	/**
	 * Очищает все внутренние данные
	 */
	public function clear()
	{
		$this->clear_params();
	}

	/**
	 * Удаляет все параметры
	 */
	public function clear_params()
	{
		$this->params = array();
	}

	/**
	 * Копирует параметры из объекта
	 * @param $params TemplateParams: Параметры
	 */
	public function assign_params($params)
	{
		$this->copy_params($params->params);
	}

	/**
	 * Копирует параметры из массива
	 * @param $params Array: Параметры
	 */
	protected function copy_params($params)
	{
		$this->params = array_merge($this->params, $params);
	}

	private function format_content($parsable)
	{
		$data = $parsable->get_data();

		$content = "";

		while (list(, $param) = each($data))
		{
			if (!is_array($param))
			{
				$content .= $param;
				continue;
			}

			$name = $param['value'];

			if (isset($param['options']))
				$options = $param['options'];
			else
				$options = array();

			if (isset($this->params[$name]))
			{
				$value = $this->params[$name];

				if (isset($options['!empty']) && !$value->is_empty())
					$value = $options['!empty'];
				else if (isset($options['empty']) && $value->is_empty())
					$value = $options['empty'];
				else if (isset($options['exist']))
					$value = $options['exist'];
				else if (isset($options['!empty']) && !isset($options['empty']) && $value->is_empty())
					$value = new Parsable();
			}
			else
			{
				if (isset($options['empty']))
					$value = $options['empty'];
				else if (isset($options['!exist']))
					$value = $options['!exist'];
				else
					$value = new Parsable();
			}

			$value = $this->format_content($value);

			if (isset($options['time']))
			{
				if ($options['time'] !== true)
					$format = $this->format_content($options['time']);
				else
					$format = SITE_DATETIME_FORMAT;

				if ($value === "0")
					$value = "";
				else if (empty($value))
					$value = date($format);
				else if (is_numeric($value))
					$value = date($format, $value);
			}
			else if (isset($options['ip']))
				$value = long2ip($value);
			else if (isset($options['cut']))
			{
				$length = $this->format_content($options['cut']);

				$length = intval($length);
				if ($length === 0)
					$length = 150;

				$value = mb_strimwidth($value, 0, $length, "...");
			}

			$content .= $value;
		}

		return $content;
	}

	/**
	 * Возвращает содержимое контейнера
	 * @retval String Содержимое
	 */
	abstract protected function get_content();

	/**
	 * Выполняет загрузку содержимого контейнера
	 */
	abstract protected function load();
}

/**
 * Предоставляет функции управления очередью вывода страниц
 *
 * @uses ObjectPages
 * @uses Parsable
 * @uses TemplateParam
 * @uses TemplateParams
 *
 * @version 1.0.1
 */
class ObjectTemplates extends TemplateParams
{
	private $template	= "";
	private $base_path	= "";

	private $structure	= array();
	private $header		= false;
	private $footer		= false;
	private $content	= null;

	/**
	 * Выводит содержимое очереди страниц на экран
	 */
	public function handler_output()
	{
		echo $this->__toString();
	}

	/**
	 * Устанавливает базовый путь для всех страниц в очереди
	 * @param $base_path String: Базовый путь
	 */
	public function set_base_path($base_path)
	{
		$this->base_path = $base_path;
	}

	/**
	 * Устанавливает шаблон вывода содержимого
	 * @param $template String: Имя шаблона
	 */
	public function set_template($template)
	{
		$this->template = $template;
	}

	/**
	 * Устанавливает страницу для отображения
	 * @param $page_name String: Имя страницы
	 */
	public function set_page($page_name)
	{
		$this->set($page_name);

		$this->header = false;
		$this->footer = false;

		if (empty($this->template))
			return;

		$template = $this->get("/".$this->template);

		$content_type = $template->get_param("Pages::Content-type");
		if ($content_type !== false)
			Component::print_headers((string) $content_type);

		if (isset($template->params['Pages::Header']))
		{
			$this->header = $template->params['Pages::Header'];
			unset($template->params['Pages::Header']);
		}

		if (isset($template->params['Pages::Footer']))
		{
			$this->footer = $template->params['Pages::Footer'];
			unset($template->params['Pages::Footer']);
		}

		$this->assign_params($template);
	}

	/**
	 * Очищает имя страницы от лишних символов
	 * @param $name String: Имя страницы
	 * @retval String Имя страницы
	 */
	public function clear_name($name)
	{
		if ($name === false)
			return "";

		$name = trim($name, "/ ");
		$name = preg_replace("/\/+/u", "/", $name);
		$name = preg_replace("/&#47;/u", "", $name);

		return $name;
	}

	/**
	 * Определяет, начинается ли имя страницы с данного префикса
	 * @param $page String: Имя страницы
	 * @param $prefix String: Префикс
	 * @retval true Страница начинается с префикса
	 * @retval false Страница не начинается с префикса
	 */
	public function starts_with($page, $prefix)
	{
		if ($page == $prefix)
			return true;

		if (strpos($page, $prefix."/") === 0)
			return true;

		return false;
	}

	/**
	 * Возвращает флаг пустоты очереди
	 * @retval true Очередь пуста
	 * @retval false Очередь не пуста
	 */
	public function is_empty()
	{
		return empty($this->structure);
	}

	/**
	 * Проверяет существование страницы
	 * @param $name String: Имя страницы
	 * @retval true Страница существует
	 * @retval false Страница не существует
	 */
	public function exist($name)
	{
		$name = $this->append_base_path($name);

		return $this->objects->Pages->exist($name);
	}

	/**
	 * Добавляет страницу в очередь
	 * @param $name String: Имя страницы
	 */
	public function add($name)
	{
		$name = $this->append_base_path($name);

		$this->content = null;
		$this->structure[] = $name;
	}

	/**
	 * Возвращает страницу по имени
	 * @param $name String: Имя страницы
	 * @retval TemplateParam Страница
	 */
	public function &get($name)
	{
		$name = $this->append_base_path($name);
		$data = $this->objects->Pages->get($name);

		$param = new TemplateParam($data['content']);
		$param->params = $data['params'];

		return $param;
	}

	/**
	 * Очищает очередь страниц
	 */
	public function clear()
	{
		parent::clear();

		$this->content = null;
		$this->structure = array();
	}

	/**
	 * Заменяет все страницы в очереди текущей
	 * @param $name String: Имя страницы
	 */
	public function set($name)
	{
		$this->structure = array();
		$this->add($name);
	}

	protected function load()
	{
		if ($this->content !== null)
			return;

		$this->content = new Parsable();

		if ($this->header !== false)
			$this->content->add($this->header);

		reset($this->structure);
		while (list(, $name) = each($this->structure))
		{
			$data = $this->objects->Pages->get($name);

			$this->content->add($data['content']);
			$this->copy_params($data['params']);
		}

		if ($this->footer !== false)
			$this->content->add($this->footer);
	}

	protected function get_content()
	{
		$this->load();
		return $this->content;
	}

	/**
	 * Добавляет к имени страницы базовый путь
	 * @param $name String: Имя страницы
	 * @retval String Полное имя страницы
	 */
	protected function append_base_path($name)
	{
		if (!empty($name) && $name[0] == "/")
			return trim($name, "/");

		return trim($this->base_path."/".$name, "/");
	}

}

/**
 * Представляет конкретный параметр
 *
 * @uses TemplateParams
 *
 * @version 1.0.1
 */
class TemplateParam extends TemplateParams
{
	private $value;

	/**
	 * Конструктор класса
	 * @param $value Parsable: Параметр
	 */
	public function __construct($value)
	{
		$this->value = $value;
	}

	protected function get_content()
	{
		return $this->value;
	}

	protected function load()
	{}
}

?>