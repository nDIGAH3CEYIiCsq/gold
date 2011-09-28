<?php

/**
 * Предоставляет функции работы с версиями страниц
 *
 * @uses DatabaseInterface
 * @uses ObjectPagination
 *
 * @version 1.0.0
 */
class ObjectPagesVersions extends Object implements DatabaseInterface
{
	static public function get_queries()
	{
		return array(
			'get'		=> "SELECT * FROM @ppages_versions WHERE id = @i",
			'add'		=> "INSERT INTO @ppages_versions SET @a",
			'get_count'	=> "SELECT count(*) as total FROM @ppages_versions WHERE name LIKE BINARY @s @A",
			'get_all'	=> "SELECT v.*, u.login FROM @ppages_versions v LEFT JOIN @pusers u ON u.id = v.user_id WHERE name LIKE BINARY @s @A ORDER BY v.id DESC @L"
		);
	}

	/**
	 * Возвращает данные версии страницы
	 * @param $id Integer: Идентификатор версии
	 * @retval Array Данные страницы
	 */
	public function get($id)
	{
		$result = $this->DB->get($id);
		if ($result->is_empty())
			return false;

		return $result->fetch();
	}

	/**
	 * Добавляет в БД новую версию страницы
	 * @param $data Array: Данные версии
	 * @retval DatabaseResult Результат выполнения запроса
	 */
	public function add($data)
	{
		return $this->DB->add($data);
	}

	/**
	 * Запрашивает из БД данные о версиях страниц
	 * @param $filter Array: Фильтр выборки
	 * @retval DatabaseResult Результат выполнения запроса
	 */
	public function get_all($filter)
	{
		$name = "";
		if (!empty($filter['name']))
			$name = $filter['name'];

		if (empty($filter['final']))
			$name .= "%";

		unset($filter['name']);
		unset($filter['final']);

		if (empty($filter['user_id']))
			unset($filter['user_id']);

		$page = $this->Pagination->init(PAGES_VERSIONS_PER_PAGE, array(), "Версии/Pagination");

		$result = $this->DB->get_count($name, $filter);
		$data = $result->fetch();

		$this->Pagination->bind($page, $data);

		$result = $this->DB->get_all($name, $filter, $page['limit']);

		return $result;
	}
}

?>