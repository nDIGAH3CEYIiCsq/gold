<?php
/**
 * Модуль отчетности менеджера
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @uses ObjectProducts
 * @uses ObjectCategories
 * @version 1.0.1
 */
class AdminQuestions extends ComponentAdmin
{
	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Вопросы клиентов");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Вопросы клиентов");
	}

	public function get_access_overrides()
	{
		return array(	'get_data'	=> "INDEX",
				'open'		=> "INDEX",
				'answer'	=> "EDIT"
		);
	}

	public function on_index()
	{
		$this->Templates->set_page("");
	}

	public function on_get_data()
	{
		$this->Questions->send_data();
	}

	public function on_answer()
	{
		if (!$this->EasyForms->field("send"))
		{
			$id = $this->EasyForms->field("id", INPUT_GET);
			$question = $this->Questions->get($id);
			if ($question === false)
				Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "questions", 'action' => "index"));

			if (!empty($question['answer']))
				Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "questions", 'action' => "index"));

			$this->Templates->set_page("Ответ на вопрос");

			$this->Templates->bind_params($question);

			return;
		}

		$fields = array(
				'answer'	=> array(),
				'id'		=> array()
		);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		$question = $this->Questions->get($fields['id']);
		if ($question === false)
			Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "questions", 'action' => "index"));

		$this->Questions->answer($fields['id'], $fields['answer']);
		Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "questions", 'action' => "index"));
	}

	public function on_open()
	{
		$id = $this->EasyForms->field("id", INPUT_GET);
		if (empty($id))
			Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "questions", 'action' => "index"));

		$question = $this->Questions->get($id);
		if ($question === false)
			Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "questions", 'action' => "index"));

		$this->Templates->set_page("Вопрос");

		$this->Templates->bind_params($question);
	}
}