<?php

/**
 * Предоставляет функции разбиения результатов на страницы
 *
 * @uses ObjectCommon
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 *
 * @version 1.0.2
 */
class ObjectPagination extends Object
{
	const DefaultTemplate = "/Объекты/Pagination";

	/**
	 * Выполняет инициализацию постраничной разбивки
	 * @param $per_page Integer: Количество элементов на странице
	 * @param $addin Array: Дополнительные GET параметры ссылок
	 * @param $template String: Шаблон постраничной разбивки
	 * @retval Array Данные постраничной разбивки
	 */
	public function init($addin = array(), $template = null)
	{
		if ($template === null)
			$template = self::DefaultTemplate;

		$data = array();

		$data['page']		= $this->get_page();
		$data['per_page']	= $this->get_count();
	//	echo $data['per_page'];
		$addin['count']		= $data['per_page'];
		$data['addin']		= $addin;
		$data['template']	= $template;

		$data['left'] = ($data['page'] - 1) * $data['per_page'];
		if ($data['left'] < 0)
			$data['left'] = 0;

		return $data;
	}

	/**
	 * Устанавливает требуемые шаблоном параметры
	 * @param[in,out] $panel Array: Данные постраничной разбивки
	 * @param $data Array: Данные о количестве элементов
	 */
	public function bind(&$panel, $data)
	{
		$this->Templates->total = $data['total'];
		$this->Templates->{"count_".$panel['per_page']} = true;
		if (!$this->check($panel, $data['total']))
			return;

		$template = $this->Templates->get($panel['template']);
		$template->addin = $this->Common->format_params($panel['addin']);

		$params = $template->get_params(array("page_previous", "page_next", "page_selected", "page_active"), false);
		if ($params === false)
			$this->Log->error("Can't find pages params");

		list($page_previous, $page_next, $page_selected, $page_active) = $params;

		if ($panel['page'] != 1)
		{
			$page_previous->previous = $panel['page'] - 1;
			$template->previous = (string) $page_previous;
		}

		if ($panel['page'] != $panel['pages'])
		{
			$page_next->next = $panel['page'] + 1;
			$page_next->pages = $panel['pages'];
			$template->next = (string) $page_next;
		}

		if ($panel['pages'] > 10)
		{
			$left	= $panel['page'] - 5;
			$right	= $panel['page'] + 5;

			if ($left < 1)
			{
				$right += -$left;
				$left = 1;
			}

			if ($right > $panel['pages'])
			{
				$left -= $right - $panel['pages'] - 1;
				$right = $panel['pages'];
			}
		}
		else
		{
			$left	= 1;
			$right	= $panel['pages'];
		}

		$table = "";
		for ($page = $left; $page <= $right; $page++)
		{
			if ($panel['page'] != $page)
			{
				$page_active->page = $page;
				$table .= (string) $page_active;
			}
			else
			{
				$page_selected->page = $page;
				$table .= (string) $page_selected;
			}
		}

		$template->total = $data['total'];
		$template->pages = $panel['pages'];
		$template->table = $table;

		$this->Templates->set_param("Pagination::Panel", (string) $template);
		$this->Templates->total = $data['total'];
	}

	private function get_page()
	{
		$page = $this->EasyForms->field("page", INPUT_GET);
		if ($page === false)
			return 1;

		$page = intval($page);
		if ($page < 1)
			return 1;

		return $page;
	}

	private function get_count()
	{
		$count = $this->EasyForms->field("count", INPUT_GET);
		if (empty($count))
			$count = 24;
		if ($count != 24 && $count != 36 && $count != 48 && $count != 96)
			$count = 24;
		return $count;
	}

	private function check(&$panel, $total)
	{
		if ($panel['per_page'] == "all")
		{
			$panel['pages'] = 1;
			$panel['page'] = 1;
			$panel['left'] = 1;
			$panel['right'] = 1;
			return true;
		}
		else
			$panel['pages'] = intval($total / $panel['per_page']);

		if ($panel['pages'] * $panel['per_page'] != $total)
			$panel['pages']++;

		if ($panel['page'] < 1)
			$panel['page'] = 1;

		if ($panel['page'] > $panel['pages'])
			$panel['page'] = $panel['pages'];

		$panel['left'] = ($panel['page'] - 1) * $panel['per_page'];
		if ($panel['left'] <= 0 || $panel['left'] >= $total)
		{
			$panel['page'] = 1;
			$panel['left'] = 0;
		}

		$panel['limit'] = array($panel['per_page'], $panel['left']);

		if ($total <= 0 || $panel['pages'] == 1)
			return false;

		return true;
	}
}

?>