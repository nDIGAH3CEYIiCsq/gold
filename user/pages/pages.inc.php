<?php

/**
 * Реализует отображение и блокировку страниц сайта
 *
 * @uses ComponentUser
 * @uses ObjectTemplates
 *
 * @version 1.0.3
 */
class UserPages extends ComponentUser
{
	/**
	 * Выводит запрашиваемую страницу на экран
	 */
	public function on_set($data)
	{
		if (!$this->Templates->exist($data['page']))
			return "nopage";

		$this->Templates->set_page($data['page']);
		return "";
	}

	/**
	 * Блокирует страницу
	 */
	public function on_block($data)
	{
		return "block";
	}
}

?>