<?php

/**
 * Предоставляет функции упаковки и распаковки данных
 *
 * @version 1.0.2
 */
class ObjectCompress extends Object
{
	private $methods = array(
		'gz'	=> array('compress' => "gzcompress", 'decompress' => "gzuncompress"),
		'bz2'	=> array('compress' => "bzcompress", 'decompress' => "bzdecompress"),
	);

	private $supported = array();

	public function __construct(&$objects)
	{
		parent::__construct($objects);

		$this->init_methods();
	}

	/**
	 * Упаковывает данные указаным методом
	 * @param $data String: Данные для упаковки
	 * @param $method String: Имя метода
	 * @retval String Упакованные данные
	 */
	public function compress($data, $method = "any")
	{
		if ($method == "none")
			return $data;

		if (!isset($this->methods[$method]))
			return $this->compress_any($data);

		$method = $this->methods[$method];
		return @$method['compress']($data);
	}

	/**
	 * Распаковывает данные указанным методом
	 * @param $data string: Данные для распаковки
	 * @param $method String: Имя метода
	 * @retval String Распакованные данные
	 */
	public function decompress($data, $method = "any")
	{
		if ($method == "none")
			return $data;

		if (!isset($this->methods[$method]))
			return $this->decompress_any($data);

		$method = $this->methods[$method];

		return @$method['decompress']($data);
	}

	/**
	 * Возвращает список поддерживаемых методов
	 * @retval Array Список поддерживаемых методов
	 */
	public function get_supported_methods()
	{
		return $this->supported;
	}

	private function compress_any($data)
	{
		reset($this->supported);
		while (list($name, ) = each($this->supported))
		{
			$method = $this->methods[$name];
			return @$method['compress']($data);
		}

		return $data;
	}

	private function decompress_any($data)
	{
		reset($this->supported);
		while (list($name, ) = each($this->supported))
		{
			$method = $this->methods[$name];

			$result = @$method['decompress']($data);
			if ($result !== false && is_string($result))
				return $result;
		}

		return $data;
	}

	private function init_methods()
	{
		while (list($name, $data) = each($this->methods))
		{
			if (!function_exists($data['compress']))
				continue;
			if (!function_exists($data['decompress']))
				continue;

			$this->supported[$name] = true;
		}
	}
}

?>