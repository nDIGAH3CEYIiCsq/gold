<?php

/**
 * Предоставляет функции управления новостями
 *
 * @uses DatabaseInterface
 * @uses ObjectPages
 *
 * @version 1.0.2
 */
class ObjectNews extends Object implements DatabaseInterface
{
	static public function get_queries()
	{
		return array(
			'news_get'		=> "SELECT p.*, u.login FROM @ppages p LEFT JOIN @pusers u ON u.id = p.user_id WHERE p.name LIKE BINARY @s ORDER BY p.created DESC",
			'news_categories'	=> "SELECT name FROM @ppages WHERE name LIKE @s",
			'add'			=> "INSERT INTO @ppages SET @a"
		);
	}

	/**
	 * Возвращает данные новостей для отображения
	 * @param $count Integer: Количество возвращаемых статей
	 * @param $category String: Категория новостей
	 * @param $skip_hidden Boolean: Определяет, пропускать ли скрытые новости
	 * @retval Array Данные новостей
	 */
	public function get($count, $category = "", $skip_hidden = true)
	{
		$prefix = NEWS_BASE_PATH;
		if ($category != "")
			$prefix .= "/".$category;

		$path_length = strlen(NEWS_BASE_PATH) + 1;

		$result = $this->DB->news_get($prefix. "/%");

		$news = array();
		while (($row = $result->fetch()))
		{
			$this->Pages->parse($row);

			$row['name'] = substr($row['name'], $path_length);

			$params = $row['params'];

			if ($skip_hidden && isset($params['News::Hide']))
				continue;

			$news[] = $row;

			$count--;
			if ($count == 0)
				break;
		}

		return $news;
	}

	/**
	 * Возвращает названия всех категорий
	 * @retval Array Названия категорий
	 */
	public function get_categories()
	{
		//$result = $this->DB->news_categories(NEWS_BASE_PATH."/%");
		$result = $this->DB->news_categories(NEWS_BASE_PATH."%");

		$path_length = strlen(NEWS_BASE_PATH) + 1;

		$categories = array();
		while (($row = $result->fetch()))
		{
			$title = substr($row['name'], $path_length);
			$pos = strpos($title, "/");
			//if ($pos === false)
			//	continue;
			if ($pos !== false)
				$category = substr($title, 0, $pos);
			else
				$category = $title;
			if (in_array($category, $categories))
				continue;

			$categories[] = $category;
		}

		return $categories;
	}

	/**
	 * Добавление новой категории новостей
	 * @return none
	 */
	public function add_category($name)
	{
		$full_name = NEWS_BASE_PATH."/".$name;
		$data = array('user_id' => $_SESSION['id'], 'created' => time(), 'name' => $full_name);
		$this->DB->add($data);
	}

	/**
	 * Добавление новой новости
	 * @param Array $fields данные новости
	 * @param <type> $params доп. параметры
	 */
	public function add(&$fields, $params)
	{
		$full_name = $this->get_path($fields['name'], $fields['category']);
		$fields['created'] = time();
		$fields['user_id'] = $_SESSION['id'];

		$html = $this->generate_html($fields);

		$this->Pages->add(array('name' => $full_name,
					'content' => $html,
					'params' => $params,
					'user_id' => $fields['user_id'],
					'created' => $fields['created']));
		$fields['login'] = $_SESSION['login'];
	}

	/**
	 * Обновление новости
	 * @param Array $fields - содержит новые данные о новости, а также старое имя страници и старое имя категории
	 * @param Array $params - параметры новости - News::Title, Niews::Short, News::Category
	 */

	public function update($fields, $params)
	{
		$full_path = $this->get_path($fields['name'], $fields['category']);
		$full_path_old = $this->get_path($fields['name_old'], $fields['category_old']);

		$html = $this->generate_html($fields);
		$data = array('name' => $full_path, 'content' => $html, 'params' => $params);

		if ($full_path != $full_path_old)
			$this->Pages->update($data, $full_path_old);
		else
			$this->Pages->update($data);
	}

	public function delete($fields)
	{
		$this->remove_files($fields['name'], $fields['category']);

		$full_path = $this->get_path($fields['name'], $fields['category']);

		$this->Pages->delete($full_path);

	}

	public function get_path($name, $category)
	{
		return NEWS_BASE_PATH."/".$category."/".$name;
	}

	public function create_news_dir($name, $category)
	{
		$category_dir = $this->get_category_dir($category);
		$this->create_dir($category_dir);

		$dir_news = $category_dir.$name."/";
		$this->create_dir($dir_news);

		return $dir_news;
	}

	public function remove_files($name, $category)
	{
		$dir_category = $this->get_category_dir($category);
		if (!file_exists($dir_category))
			return;

		$dir_news = $this->get_news_dir($name, $category);
		$this->Files->remove_directory($dir_news, true);

		if ($this->is_empty_dir($dir_category))
		{
			$this->Files->remove_directory($dir_category);
			return;
		}
	}

	public function remove_image($name, $category)
	{
		$dir_news = $this->get_news_dir($name, $category);
		$dir_category = $this->get_category_dir($category);
		if (!file_exists($dir_category))
			return;

		$dir_image = $dir_news.NEWS_IMAGE_DIRECTORY;
		if (file_exists($dir_image))
			$this->Files->remove_directory($dir_image, true);

		if (file_exists($dir_news) && $this->is_empty_dir($dir_news))
			$this->Files->remove_directory($dir_news);

		if ($this->is_empty_dir($dir_category))
		{
			$this->Files->remove_directory($dir_category);
			return;
		}
	}

	public function save_image($fields)
	{
		$file = $this->Files->get_data("image", array());

		if ($file === false)
			throw new Exception("Ошибка запроса загрузки картинки");

		$dir_news = $this->create_news_dir($fields['name'], $fields['category']);
		$dir = $dir_news.NEWS_IMAGE_DIRECTORY;

		if (!file_exists($dir) && @mkdir($dir) === false)
			throw new Exception("Не удалось создать директория для изображения $dir");

		$image_name = $dir.NEWS_IMAGE_NAME;

		$result = $this->Files->upload_file($file, $image_name);
		if ($result !== true)
			throw new Exception($result);

		if (!$this->Images->image_copy_resampled($image_name, $image_name, NEWS_IMAGE_WIDTH, NEWS_IMAGE_HEIGHT))
		{
			@unlink($image_name);
			throw new Exception("Не удалось сохранить изображение");
		}
	}

	public function save_files($files_labels)
	{
		$data = array();
		try
		{
			$data['files'] = array();
			while (list($index, $name) = each($fields['files_names']))
			{
				$folder = $dir_news.NEWS_FILES_DIRECTORY;
				$name = $_FILES['files']['name'][$index];
				$file_correct = $this->save_file("files", array($index), $folder, $name);
				if ($file_correct !== true)
					break;

				if (!isset($fields['files_names'][$index]))
					throw new Exception("Файлы заданы не корректно");

				$data['files'][] = array('label' => $fields['files_names'][$index], 'file' => $name);
			}
			if ($file_correct !== true)
				throw new Exception($file_correct);

			$data['links'] = array();
			reset($fields['links_names']);
			while(list($index, $name) = each($fields['links_names']))
			{
				if (!isset($fields['links'][$index]))
					throw new Exception("Ссылки заданы не корректно");

				$data['links'][] = array('label' => $name, 'link' => $fields['links'][$index]);
			}

			if (!empty($_FILES['image']['tmp_name']))
			{
				$dir = $dir_news.NEWS_IMAGE_DIRECTORY;
				$result = $this->save_file("image", array(), $dir, NEWS_IMAGE_NAME);
				if ($result !== true)
					throw new Exception($result);

				$fields['image'] = $this->get_url_image($fields);
			}
		}
		catch(Exception $e)
		{
			$this->Files->remove_directory($dir_news);
			$this->XML->send_error($e->getMessage());
		}
	}

	public function get_news_dir($name, $category)
	{
		$dir = $this->get_category_dir($category);
		$dir_news = $dir.$name."/";

		return $dir_news;
	}

	public function get_category_dir($category)
	{
		return MAIN_LOCATION.NEWS_DIRECTORY.$category."/";
	}

	private function is_empty_dir($dir)
	{
		$dh = opendir($dir);
		if (!$dh)
			$this->Log->error("Can't find directory $dir");

		$files = array();

		while (($file = readdir($dh)))
		{
			if ($file == "." || $file == "..")
				continue;

			if ($file[0] == ".")
				continue;

			return false;
		}

		return true;
	}

	private function create_dir($dir)
	{
		if (!file_exists($dir) && @mkdir($dir) === false)
			throw new Exception("Не удалось создать директорию $dir");
	}

	private function generate_html($fields)
	{
		$template = $this->Templates->get("");
		$params = $template->get_params(array('image', "news"));
		if ($params === false)
			$this->Log->error("Can't find news params");

		$bind_data = array('title' => $fields['name']);

		if (!empty($fields['title']))
			$bind_data['title'] = $fields['title'];

		$image_template = $params['image'];
		if (isset($fields['image']))
		{
			$img_params = array('src' => $fields['image']);
			if (!empty($fields['clear']))
				$img_params['clear'] = true;

			if (in_array($fields['position'], array('left', 'right', 'center')))
				$img_params[$fields['position']] = true;
			else
				$this->Log->error("Не корректное положение картинки");

			$image_template->bind_params($img_params);
			$bind_data['image_news'] = (string) $image_template;
		}

		$bind_data['text'] = $fields['text'];
		if (empty($bind_data['text']))
			$bind_data['text'] = $fields['short_text'];

		$news_template = $params['news'];
		$news_template->bind_params($bind_data);
		return (string)$news_template;
	}
}

?>