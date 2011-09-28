<?php

/**
 * Модуль выхода из панели администрирования
 *
 * @uses ComponentAdmin
 * @uses ObjectAdmin
 *
 * @version 1.0.1
 */
class AdminLogout extends ComponentAdmin
{
	public function get_services()
	{
		return array('index' => "Выход");
	}

	public function get_access_overrides()
	{
		return array(
			'index' => false
		);
	}

	/**
	 * Отображает титульную страницу модуля
	 */
	public function on_index()
	{
		$this->Admin->logout();
		Component::redirect($_SERVER['SCRIPT_NAME']);
	}
}

?>