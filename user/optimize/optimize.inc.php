<?php

/**
 * Реализует прозрачную оптимизацию страниц при выводе
 *
 * @uses ComponentUser
 *
 * @version 1.0.1
 */
class UserOptimize extends ComponentUser
{
	/**
	 * Устанавливает обработчик сжатия страницы методом GZip
	 */
	public function on_gzip()
	{
		ob_start("ob_gzhandler");
	}

	/**
	 * Очищает HTML код страницы при
	 */
	public function on_clear_html()
	{
		ob_start(array($this, "clear_html"));
	}

	/**
	 * Очищает HTML код страницы от лишних пробелов и переводов строк
	 * @param $buffer String: Данные для очистки
	 * @retval String Очищенные данные
	 */
	public function clear_html($buffer)
	{
		if (!$this->check_headers())
			return $buffer;

		return $this->clean_preg($buffer);
	}

	private function clean_preg($buffer)
	{
		$buffer = preg_replace("/\s+/u", " ", $buffer);
		$buffer = preg_replace("/(<script[^>]+>)\s*<!--\s*(.+?)\s*\/\/-->\s*(<\/script>)/ui", "\\1<!--\n\\2\n//-->\\3", $buffer);
		$buffer = preg_replace("/\s*(<\/?(br|p|tr|td|table|meta|title|script|link|html|body|head|form|tbody|thead|tfoot)[^>]*>)\s*/ui", "\\1", $buffer);

		return $buffer;
	}

	private function check_headers()
	{
		$headers = headers_list();
		while (list(, $header) = each($headers))
		{
			$pos = strpos($header, "Content-type:");
			if ($pos !== 0)
				continue;

			$type = substr($header, 14);

			$pos = strpos($type, ";");
			if ($pos !== false)
				$type = substr($type, 0, $pos);

			if ($type == "text/html")
				break;

			return false;
		}

		return true;
	}
}

?>