<?php

/**
 * Реализует конвертацию русских URL в escape-последовательность
 *
 * @uses ComponentUser
 * @uses ObjectEasyForms
 * @uses ObjectTemplates
 * @uses ObjectUrls
 *
 * @version 1.0.1
 */
class UserConvert extends ComponentUser
{
	public function on_index($data)
	{
		$page = $this->EasyForms->field("page");
		if ($page === false)
			return;

		$this->Templates->page = $page;

		$parsed = parse_url($page);
		if ($parsed === false)
			return;

		if (isset($parsed['path']))
			$parsed['path'] = $this->Urls->normalize($parsed['path']);

		$page_converted = "";
		if (isset($parsed['scheme']))
			$page_converted .= $parsed['scheme']."://";
		if (isset($parsed['user']))
			$page_converted .= $parsed['user'];
		if (isset($parsed['pass']))
			$page_converted .= ":".$parsed['pass'];
		if (isset($parsed['host']))
			$page_converted .= $parsed['host'];
		if (isset($parsed['path']))
			$page_converted .= $parsed['path'];
		if (isset($parsed['query']))
			$page_converted .= "?".$parsed['query'];
		if (isset($parsed['fragment']))
			$page_converted .= "#".$parsed['fragment'];

		$this->Templates->page_converted = $page_converted;
	}
}

?>