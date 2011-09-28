<?php

/**
 * Предоставляет функции работы с датой
 *
 * @uses ObjectLog
 *
 * @version 1.0.2
 */
class ObjectDate extends Object
{
	private $months = array(
		'genetive'	=> array("января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа", "сентября", "октября", "ноября", "декабря"),
		'nominative'	=> array("январь", "февраль", "март", "апрель", "май", "июнь", "июль", "август", "сентябрь", "октябрь", "ноябрь", "декабрь"),
	);

	private $days		= array("воскресенье", "понедельник", "вторник", "среда", "четверг", "пятница", "суббота");

	private $month_days	= array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

	/**
	 * Возвращает название дня недели
	 * @param $day Integer: Номер дня
	 * @retval String Название дня недели в единственном числе именительного падежа
	 */
	public function get_day_name($day)
	{
		if ($day < 0)
			$day += count($this->days) - 1;

		return $this->days[$day];
	}

	/**
	 * Возвращает название месяца
	 * @param $month Integer: Номер месяца
	 * @param $case String: Падеж слова
	 * @retval String Название месяца в единственном числе указанного падежа
	 */
	public function get_month_name($month, $case)
	{
		if (!isset($this->months[$case]))
			$this->Log->error("Unkonwn case {$case}");

		$months = &$this->months[$case];
		if ($month < 1)
			$month += count($months);

		return $months[$month - 1];
	}

	/**
	 * Возвращает количество дней в месяце
	 * @param $month Integer: Номер месяца
	 * @retval String Количество дней в месяце
	 */
	public function get_month_days($month)
	{
		if ($month < 1)
			$month += count($this->month_days);

		return $this->month_days[$month - 1];
	}
}

?>