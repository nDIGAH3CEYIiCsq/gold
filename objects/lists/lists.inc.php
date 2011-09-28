<?php


/**
 * Предоставляет функции генерации списков
 *
 * @version 1.0.2
 */
class ObjectLists extends Object
{
	/**
	 * Генерирует список на основе шаблона
	 * @param $template String: Шаблон, на основе которого генерировать список
	 * @param $data Array: Данные для списка
	 * @param $selected Mixed: Текущее значение списка
	 * @retval String Текст списка
	 */
	public function make($template, $data, $selected = false)
	{
		if (!is_array($selected))
			$selected = array($selected);
		
		$list = "";

		reset($data);
		while ((list($name, $value) = each($data)))
		{
			$template->clear();
			$template->name = $name;
			$template->value = $value;
			if (in_array($value, $selected))
				$template->selected = true;

			$list .= (string) $template;
		}

		return $list;
	}
}
?>