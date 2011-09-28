<?php

/**
 * Предоставляет функции генерации ответов для server-side таблиц
 *
 * @uses ObjectCommon
 * @uses ObjectEasyForms
 * @uses ObjectTemplates
 *
 * @version 1.0.6
 */
class ObjectTables extends Object
{
	private $columns_count = false;
	private $echo = false;

	/**
	 * Отправляет данные для server-side таблицы
	 * @param $table Array: Данные таблицы<br />
	 *	Требуемые элементы:<br />
	 *	fields		- Поля таблицы<br />
	 *	count		- Функция получения количества результатов<br />
	 *	data		- Функция получения данных<br />
	 *	params		- Дополнительные параметры запроса<br />
	 *	template	- Имя шаблона вывода данных
	 */
	public function send($table)
	{
		list($filters, $sort, $page) = $this->init($table['fields']);

		$params = array();
		if (isset($table['params']))
			$params = $table['params'];

		$result = call_user_func_array($table['count'], $params);
		$data = $result->fetch();

		$params[] = $filters;

		if (!empty($filters))
		{
			$result = call_user_func_array($table['count'], $params);
			$data['display'] = $result->fetch("total");
		}
		else
			$data['display'] = $data['total'];

		$params[] = $sort;
		$params[] = $page;

		$result = call_user_func_array($table['data'], $params);

		$this->send_result($data, $result, $table);
	}

	private function init($columns)
	{
		$fields = $this->get_fields($columns);
		if ($fields === false)
			$this->send_empty();

		$this->columns_count = count($columns);
		$this->echo = $fields['sEcho'];

		$fields['iColumns']		= intval($fields['iColumns']);
		$fields['iSortCol_0']		= intval($fields['iSortCol_0']);
		$fields['sSortDir_0']		= strtoupper($fields['sSortDir_0']);
		$fields['iDisplayStart']	= intval($fields['iDisplayStart']);
		$fields['iDisplayLength']	= intval($fields['iDisplayLength']);

		if ($fields['iSortCol_0'] < 0 || $fields['iSortCol_0'] > $fields['iColumns'])
			$fields['iSortCol_0'] = 0;
		if ($fields['iDisplayStart'] < 0)
			$fields['iDisplayStart'] = 0;
		if ($fields['iDisplayLength'] < 0)
			$fields['iDisplayLength'] = 10;

		$filters = array();

		for ($i = 0; $i < $fields['iColumns']; $i++)
		{
			if (!isset($fields['sSearch_'.$i]) || $fields['sSearch_'.$i] == "")
				continue;

			$fields['sSearch_'.$i] = str_replace("*", "%", $fields['sSearch_'.$i]);

			$filters[$columns[$i]] = $fields['sSearch_'.$i];
		}

		$sort = $columns[$fields['iSortCol_0']];
		$sort = array($sort, $fields['sSortDir_0']);

		$page = array($fields['iDisplayLength'], $fields['iDisplayStart']);

		return array($filters, $sort, $page);
	}

	private function send_result($data, $result, $table)
	{
		$template = "";
		if (isset($table['template']))
			$template = $table['template'];

		if ($template !== false)
		{
			$template = $this->Templates->get($template);

			$templates = array();
			for ($i = 0; $i < $this->columns_count; $i++)
				$templates[$i] = $template->get_param("col_".($i + 1));
		}

		$data['data'] = array();

		while (($row = $result->fetch_row()))
		{
			if ($template !== false)
				$this->Common->apply_templates($row, $templates);

			$data['data'][] = $row;
		}

		$output = array();
		$output['sEcho']		= $this->echo;
		$output['iTotalRecords']	= $data['total'];
		$output['iTotalDisplayRecords']	= $data['display'];
		$output['aaData']		= $data['data'];

		Component::print_headers();

		echo json_encode($output);
		exit;
	}

	private function send_empty()
	{
		Component::print_headers();

		echo json_encode(array('aaData' => array()));
		exit;
	}

	private function get_fields($columns)
	{
		$fields = array(
			'sEcho'			=> array(),
			'iColumns'		=> array(),
			'iDisplayStart'		=> array(),
			'iDisplayLength'	=> array(),
			'iSortCol_0'		=> array(),
			'sSortDir_0'		=> array()
		);

		$columns_count = count($columns);
		for ($i = 0; $i < $columns_count; $i++)
			$fields['sSearch_'.$i] = array('require' => false);

		return $this->EasyForms->fields($fields);
	}
}

?>