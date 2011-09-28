<?php

/**
 * Модуль управления кэшем
 *
 * @uses ComponentAdmin
 * @uses ObjectCache
 * @uses ObjectTemplates
 *
 * @version 1.0.1
 */
class AdminCache extends ComponentAdmin
{
	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Кэш");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Кэш");
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
		$this->Templates->set_page("");

		$stats = $this->Cache->get_stats();

		$this->Templates->bind_params($stats['localhost:11211']);
	}
}

?>