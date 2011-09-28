<?php

/**
 * Модуль главной страницы панели администратора
 *
 * @uses ComponentAdmin
 * @uses ObjectAdmin
 * @uses ObjectCommon
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 *
 * @version 1.0.1
 */
class AdminAdmin extends ComponentAdmin
{
	private $modules;
	private $accesses;

	public function __construct(&$copy = null)
	{
		parent::__construct($copy);

		$this->modules = &$copy->modules;
		$this->accesses = &$copy->accesses;
	}

	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array();
	}

	public function get_access_overrides()
	{
		return array(
			'main'		=> false,
			'save_state'	=> false
		);
	}

	/**
	 * Отображает титульную страницу модуля
	 */
	public function on_main()
	{
		$this->Templates->set_page("Главная");

		$admins_link = $this->Templates->admins_link;
		if ($admins_link === false)
			$this->Log->error("Can't find 'admins_link' param");

		$admins_links = array();

		reset($this->modules);
		while (list($module, $links) = each($this->modules))
		{
			if (!isset($this->accesses[$module]))
				continue;

			$accesses = $this->accesses[$module];
			if (empty($accesses))
				continue;

			reset($links);
			while (list($action, $caption) = each($links))
				array_push($admins_links, array('module' => $module, 'action' => $action, 'caption' => $caption));
		}

		$links = "";
		while (list(, $link) = each($admins_links))
		{
			$link['caption_words'] = $this->Common->mb_wordwrap($link['caption'], 9);

			$admins_link->bind_params($link);

			$links .= (string) $admins_link;
		}

		$this->Templates->links = $links;

		$data = $this->Admin->data_get("links_state");
		if ($data === false)
			return;

		$link_state = $this->Templates->link_state;
		if ($link_state === false)
			$this->Log->error("Can't find 'link_state' param");

		$states = array();
		while (list($link_id, $pos) = each($data))
		{
			$link_state->link_id = $link_id;
			$link_state->bind_params($pos);

			$states[] = (string) $link_state;
		}

		$this->Templates->states = implode(", ", $states);
	}

	/**
	 * Сохраняет положение иконки сервиса
	 */
	public function on_save_state()
	{
		$fields = array(
			'element'	=> array(),
			'top'		=> array(),
			'left'		=> array()
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			exit;

		$data = $this->Admin->data_get("links_state");
		if ($data === false)
			$data = array();

		$data[$fields['element']] = array('top' => $fields['top'], 'left' => $fields['left']);

		$this->Admin->data_set("links_state", $data);
		exit;
	}
}

?>