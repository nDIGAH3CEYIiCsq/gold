<?php

/**
 * Предоставляет функции работы с файлами
 *
 * @version 1.0.2
 */
class ObjectFiles extends Object
{
	/**
	 * Удаляет директорию
	 * @param $directory String: Имя директории
	 * @param $recursive Boolean: Определяет, удалять рекурсивно все файлы и директирии внутри или нет
	 * @retval true Удаление прошло успешно
	 * @retval false Удаление завершилось с ошибкой
	 */
	public function remove_directory($directory, $recursive = false)
	{
		if (!file_exists($directory))
			return true;

		if (!is_dir($directory))
			return @unlink($directory);

		if (!$recursive)
			return !@rmdir($directory);

		if (!is_readable($directory))
			return false;

		$handle = @opendir($directory);
		if (!$handle)
			return false;

		while (($item = readdir($handle)) != false)
		{
			if ($item == '.' || $item == '..')
				continue;

			$file = $directory.$item;

			if (!file_exists($file))
				continue;

			if (!is_dir($file))
			{
				@unlink($file);
				continue;
			}

			$this->remove_directory($file."/", $recursive);
		}

		closedir($handle);

		return @rmdir($directory);
	}

	/**
	 * Удаляет все файлы из директории
	 * @param $directory String: Имя директории
	 * @param $except_files Array: Список исключений
	 * @retval true Удаление прошло успешно
	 * @retval false Удаление завершилось с ошибкой
	 */
	public function clear_directory($directory, $except_files = array())
	{
		if (!file_exists($directory))
			return false;

		if (!is_readable($directory))
			return false;

		$handle = @opendir($directory);
		if (!$handle)
			return false;

		$exist_files = true;
		while (($item = readdir($handle)) != false)
		{
			if ($item == '.' || $item == '..')
				continue;

			$file = $directory.$item;

			if (in_array($file, $except_files))
			{
				$exist_files = true;
				continue;
			}

			if (!is_dir($file))
			{
				@unlink($file);
				continue;
			}

			if ($this->clear_directory($file."/", $except_files))
				$exist_files = true;
		}

		closedir($handle);

		if (!$exist_files)
			@rmdir($directory);

		return $exist_files;
	}

	/**
	 * Получает данные о загруженном файле
	 * @param $name String: Имя поля для загрузки файла
	 * @param $path Array: Путь в массиве полей
	 * @retval Array Данные о загруженном файле
	 */
	public function get_data($name, $path = array())
	{
		if (!isset($_FILES[$name]))
			return false;

		$storage = &$_FILES[$name];

		$data = array();

		reset($storage);
		while (list($key, $value) = each($storage))
		{
			$cur = &$storage[$key];

			reset($path);
			while (list(, $element) = each($path))
			{
				if (!isset($cur[$element]))
					return false;

				$cur = &$cur[$element];
			}

			$data[$key] = $cur;
		}

		return $data;
	}

	/**
	 * Перемещает загруженный файл
	 * @param $file Array: Данные файла
	 * @param $file_name String: Новое имя файла
	 * @retval true Если файл успещно перемещён
	 * @retval String Описание возникшей ошибки, в случае ошибки
	 */
	public function upload_file($file, $file_name)
	{
		if (!is_uploaded_file($file['tmp_name']))
			return "Не указан файл для загрузки";

		if (mb_strlen($file['name']) > FILES_MAX_NAME_LENGTH)
			return "Файл имеет слишком длинное имя";

		//if ($file['size'] > FILES_MAX_SIZE)
		//	return "Файл имеет слишком большой размер";

		if (file_exists($file_name) && !@unlink($file_name))
			return "Не удалось удалить старый файл";

		if (!move_uploaded_file($file['tmp_name'], $file_name))
			return "Не удалось скопировать файл из временной папки";

		return true;
	}

	public function get_file($source, $destination)
	{
		$f = false;
		try
		{
			//$ctx = stream_context_create(array('http' => array('timeout' => 90)));
			$ctx = stream_context_create();

			$content = file_get_contents($source, null, $ctx);
			if ($content === false)
				return false;

			$f = fopen($destination, "wb");
			fwrite($f, $content);
		}
		catch(Exception $e)
		{
			if ($f !== false)
				fclose($f);
			return false;
		}

		return true;
	}

	/**
	 * Получение всех файлов в директории
	 * @param String $directory директория
	 * @return Array - массив всех файлов в директории
	 */
	public function get_all_files($directory)
	{
		$dh = opendir($directory);
		if (!$dh)
			$this->Log->error("Can't find directory $dir", true);

		$files = array();

		while (($file = readdir($dh)))
		{
			if ($file == "." || $file == "..")
				continue;

			if ($file[0] == ".")
				continue;

			$file_path = $dir."/".$file;

			if (!is_dir($file_path))
			{
				$files[] = $file_path;
				continue;
			}

			$inner_files = $this->get_all_files($file_path);
			$files = array_merge($files, $inner_files);
		}

		closedir($dh);
		return $files;
	}
}

?>