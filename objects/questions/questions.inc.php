<?php

/**
 * Предоставляет функции для работы вопросами
 * @uses ObjectCache
 * @uses ObjectCategories
 * @uses ObjectEasyForms
 * @uses ObjectFilesis
 * @uses ObjectImages
 * @uses ObjectPagination
 * @uses ObjectProducts
 * @uses ObjectTemplates
 * @version 1.0.1
 */
class ObjectQuestions extends Object implements DatabaseInterface
{
	const CacheClass = "questions";

	static public function get_queries()
	{
		return array(
			'get'			=> "SELECT * FROM @pquestions WHERE id = @i",

			'get_data'		=> "SELECT add_time, question, answer, name, phone, email, order_id, answer_time, id, id FROM @pquestions @@W @O @L",
			'get_data_count'	=> "SELECT count(*) as total FROM @pquestions @@W",

			'answer'		=> "UPDATE @pquestions SET answer = @s, answer_time = @i WHERE id = @i",

			'add'			=> "INSERT INTO @pquestions SET @a"
		    );
	}

	public function __construct(&$objects)
	{
		parent::__construct($objects);
	}

	/**
	 * Отправляет все товары в JSON формате
	 */
	public function send_data()
	{
		$table = array(
			'fields'	=> array("add_time", "question", "answer", "name", "phone", "email", "order_id", "answer_time", "id", "id"),
			'count'		=> array(&$this->DB, "get_data_count"),
			'data'		=> array(&$this->DB, "get_data")
		);

		$this->Tables->send($table);
	}

	public function get($id)
	{
		$result = $this->DB->get($id);
		$row = $result->fetch();
		if ($row === false)
			return false;

		return $row;
	}

	public function answer($id, $answer)
	{
		$question = $this->get($id);
		if ($question === false)
			return;

		$this->DB->answer($answer, time(), $id);

		if (empty($question['email']))
			return;

		$question['title'] = "Обратная связь с интернет магазином 585.ru";
		$question['answer'] = $answer;
		$this->Mail->send($question['email'], "/Страницы/Шаблоны/Письма/Обратная связь/Ответ", $question);
	}

	public function add($data)
	{
		$data['add_time'] = time();
		$this->DB->add($data);
	}
}
?>
