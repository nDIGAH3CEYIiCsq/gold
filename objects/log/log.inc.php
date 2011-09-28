<?php

/**
 * Предоставляет функции логгирования ошибок
 *
 * @version 1.0.2
 */
class ObjectLog extends Object
{
	/**
	 * Сообщает об ошибке
	 * @param $message String: Текст сообщения
	 */
	public function error($message)
	{
		die($message);
	}

	/**
	 * Сообщает о подсказке
	 * @param $message String: Текст сообщения
	 */
	public function notice($message)
	{}

	/**
	 * Сообщает о предупреждении
	 * @param $message String: Текст сообщения
	 */
	public function warning($message)
	{}

	/**
	 * Сообщает об информации
	 * @param $message String: Текст сообщения
	 */
	public function info($message)
	{
		echo $message."<br />";
	}
}

?>