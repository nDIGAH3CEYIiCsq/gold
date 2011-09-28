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
class UserYandexMarket extends ComponentUser
{
	const CacheClass = "yandexmarket";

	const LOCAL_DELIVERY_COST = 200;
	const ORDER_SUM_MIN = 1000;

	const FILE_LOG = "img/log/url.txt";

	public function on_send_data()
	{
		@unlink(MAIN_LOCATION.self::FILE_LOG);

		$catalog = $this->XML->create(	"<?xml version='1.0' encoding='windows-1251'?>".
						"<!DOCTYPE yml_catalog SYSTEM 'shops.dtd'><yml_catalog />");
		$catalog->addAttribute("date", date("Y-m-d H:i"));

		$shop = $catalog->addChild("shop");
		$shop->addChild("name", "585.ru");
		$shop->addChild("company", "Новые технологии");
		$shop->addChild("url", "http://www.585.ru");
		$currencies = $shop->addChild("currencies");
		$currency = $currencies->addChild("currency");
		$currency->addAttribute("id", "RUR");
		$currency->addAttribute("rate", "1");

		$jewelry_id = 1;
		$categories = $shop->addChild("categories");
		$jewelry_node = $categories->addChild("category", "ювелирные изделия");
		$jewelry_node->addAttribute("id", $jewelry_id);

		$jewelry_category = $this->Categories->get($jewelry_id);
		$children_base = $jewelry_category->get_children();

		$item = urlencode("Изделие");

		while (list(,$child) = each($children_base))
		{
			$new_category = $categories->addChild("category", $child->name);;
			$new_category->addAttribute("id", $child->id);
			$new_category->addAttribute("parentId", $jewelry_id);

			$children = $child->get_children();
			while (list(, $child2) = each($children))
			{
				$new_category2 = $categories->addChild("category", $child2->name);
				$new_category2->addAttribute("id", $child2->id);
				$new_category2->addAttribute("parentId", $child->id);
			}
		}

		$shop->addChild("local_delivery_cost",  self::LOCAL_DELIVERY_COST);

		$products = $this->Products->get_all();
		$offers = $shop->addChild("offers");

		while (list(, $product) = each($products))
		{
			$offer = $offers->addChild("offer");

			$offer->addAttribute("id", $product['id']);
			$offer->addAttribute("available", "true");
			$offer->addAttribute("type", "vendor.model");
			$offer->addAttribute("bid", 10);

			$name = trim($product['name']);
			$name = urlencode($name);
			$name = str_replace("%2F", "/", $name);

			$code = $product['code'];

			$url = "http://www.585.ru/$item/$name+$code/";
			$this->log($url);

			$offer->addChild("url",$url );
			$offer->addChild("price", $product['price']);
			$offer->addChild("currencyId", "RUR");
			$offer->addChild("categoryId", $product['parent_id']);

			$image_url = $this->Products->get_image_big($product['id']);
			$offer->addChild("picture", "http://www.585.ru".$image_url);

			$offer->addChild("delivery", "false");
			//$offer->addChild("local_delivery_cost", self::LOCAL_DELIVERY_COST);

			$category_parent = $this->Categories->get($product['parent_id']);
			$offer->addChild("typePrefix", $category_parent->name);

			$offer->addChild("vendor", "585.ru");
			$offer->addChild("vendorCode", $product['code']);
			$offer->addChild("model", $product['name'] . " " .$product['code']);

			$offer->addChild("sales_notes", "минимальная сумма заказа 1000 руб.");
			$offer->addChild("manufacturer_warranty", "true");
			$offer->addChild("country_of_origin", "Россия");

			$this->addParam($offer, $product, "gem", "Описание");
			$this->addParam($offer, $product, "weigth", "Вес");

			if (!empty($product['metal_id']))
			{
				$metal = $this->Dictionaries->Metals->get_by_id($product['metal_id']);
				$product['metal'] = $metal['name'];
				$this->addParam($offer, $product, "metal", "Метал");
			}
		}

		$this->write_to_file($catalog->asXML());

		$this->XML->send_xml($catalog);
	}

	private function addParam(&$offer, $product, $name_field, $name_declaration)
	{
		if (empty($product[$name_field]))
			return;

		$param = $offer->addChild("param", $product[$name_field]);
		$param->addAttribute("name", $name_declaration);
	}

	private function log($message, $log = true)
	{
                if ($log)
                    $file = MAIN_LOCATION.self::FILE_LOG;
                else
                    $file = MAIN_LOCATION.self::FILE_RESULT;

		$fn = fopen($file, "a");
		if ($fn === false)
                    $this->Log->error("Can't open file ".$file);

		fwrite($fn, $message."\n");
		fclose($fn);
	}

	function write_to_file($data)
	{
		$file = MAIN_LOCATION."img/log/yandexmarket.txt";

		@unlink($file);

		$fh = fopen($file, 'a') or die("can't open file");
		fwrite($fh, $data);
		fclose($fh);
	}
}
?>