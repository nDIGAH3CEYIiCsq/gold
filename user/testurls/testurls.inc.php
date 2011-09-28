<?php

/**
 * Реализует выдачу товаров в виде XML для yandex.market
 *
 * @uses ComponentUser
 * @uses ObjectTemplates
 * @uses ObjectXML
 *
 * @version 1.0.3
 */
class UserTestUrls extends ComponentUser
{
	const CacheClass = "yandexmarket";

	const LOCAL_DELIVERY_COST = 200;

	public function on_send_data()
	{
		$products = $this->Products->get_all();

		$xml = $this->XML->start_answer();

		$item = urlencode("Изделие");
		while (list(, $product) = each($products))
		{
			$name = trim($product['name']);
			$name = urlencode($name);
			$name = str_replace("%2F", "/", $name);

			$code = $product['code'];

			$url = "http://www.585.ru/$item/$name+$code/";

			$xml->addChild("url", $url);
		}

		$this->XML->send_xml($xml);
	}
}
?>