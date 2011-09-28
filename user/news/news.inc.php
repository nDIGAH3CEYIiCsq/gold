<?php

/**
 * Реализует отображение списка последних новостей
 *
 * @uses ComponentUser
 * @uses ObjectCache
 * @uses ObjectCommon
 * @uses ObjectLog
 * @uses ObjectNews
 * @uses ObjectTemplates
 *
 * @version 1.0.1
 */
class UserNews extends ComponentUser
{
	const CacheClass = "modules_user_news";

	/**
	 * Устанавливает парметры, предоставляемые модулем
	 */
	public function on_index()
	{
		$template = $this->Templates->get("Шаблоны/Новости");

		$data = $this->news_list($template, NEWS_LAST_COUNT);

		$this->Templates->set_param("News::Last", $data);

		return "nopage";
	}

	/**
	 * Отображение всех новостей
	 * @param $data Array: Внутренняя информация о вызове
	 */
	public function on_all($data)
	{
		$news_list = $this->news_list($this->Templates, 0);
		$this->Templates->items = $news_list;
	}

	private function news_list(&$templates, $max_count = 0, $postfix = "")
	{
		$news_list = $this->Cache->get($max_count."_".$postfix, self::CacheClass);
		if ($news_list !== false)
			return $news_list;

		$news_item = $templates->news_item;
		if ($news_item === false)
			$this->Log->error("Can't find 'news_item' param");

		$news = $this->News->get($max_count, $postfix);
		$news_list = "";
		while (list(, $row) = each($news))
		{
			$news_item->clear();

			$pos = strrpos($row['name'], "/");
			if ($pos == FALSE)
				continue;

			$params = $row['params'];
			$data = $this->Common->copy_fields($row, array("name", "created", "updated"));
			if (isset($params['News::Title']))
				$data['title'] = $params['News::Title'];
			else
				$data['title'] = trim(strrchr($row['name'], "/"), "/");

			$data['content'] = $params['News::Short'];
			if (isset($params['News::UseOnlyShort']))
				$data['has_full'] = false;
			else
				$data['has_full'] = true;

			$data['category'] = $postfix;
			$data['name'] = $row['name'];
			if ($postfix == "")
			{
				$pos = strpos($row['name'], "/");
				if ($pos !== false)
					$data['category'] = substr($row['name'], 0, $pos);


			}
			$data['created'] = date("d m Y", $data['created']);
			$data['full'] = $row['content'];

			$news_item->bind_params($data);
			$news_list .= (string) $news_item;
		}

		$this->Cache->set($max_count."_".$postfix, self::CacheClass, $news_list);

		return $news_list;
	}
}

?>