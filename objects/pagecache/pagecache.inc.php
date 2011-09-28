<?php

/**
 * Предоставляет функции прозрачного кэширования всей страницы
 *
 * @uses ObjectCache
 *
 * @version 1.0.2
 */
class ObjectPageCache extends Object
{
	private $name;
	private $class;
	private $expire_time;

	/**
	 * Инициализирует кэш страницы
	 * @param $name String: Имя кэша
	 * @param $class String: Класс кэша
	 * @param $expire_time Integer: Время жизни данных в кэше
	 */
	public function init($name, $class, $expire_time = 0)
	{
		$data = $this->Cache->get($name, $class);
		if ($data !== false)
		{
			echo $data;
			exit;
		}

		register_shutdown_function(array($this, "save"));

		ob_start();

		$this->name = $name;
		$this->class = $class;
		$this->expire_time = $expire_time;
	}

	/**
	 * Сохраняет отправленные на вывод данные в кэше
	 */
	public function save()
	{
		$data = ob_get_contents();
		$this->Cache->set($this->name, $this->class, $data, $this->expire_time);
	}
}

?>