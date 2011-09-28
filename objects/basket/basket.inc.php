<?php

/**
 * Реализует хранение выбранных товаров в интернет магазине
 * @uses ObjectAccounts
 * @uses ObjectCategories
 * @uses ObjectProducts
 * @version 1.0.0
 */
class ObjectBasket extends Object
{
	const ITEMS_COUNT = 20;

	private $data;

	public function __construct(&$objects)
	{
		parent::__construct($objects);

		$this->objects->Accounts->init();

		if (!isset($_SESSION['basket']))
			$_SESSION['basket'] = array();

		$this->data = &$_SESSION['basket'];
	}

	public function get($product_id, $size = 0)
	{
		$this->prep_size($size);

		$data = &$this->data;
		if ($data === false)
			return;

		if (!isset($data[$product_id]))
			return false;

		if (!isset($data[$product_id][$size]))
			return false;

		return $data[$product_id][$size];
	}

	public function set($product_id, $count, $size = 0)
	{
		$count = intval($count);
		if ($count < 0)
			return;

		$data = &$this->data;
		if ($data === false)
			return;

		$this->prep_size($size);

		if ($count == 0)
		{
			$this->delete($product_id, $size);
		}

		if (!isset($data[$product_id]))
			$data[$product_id] = array();

		if ($count > self::ITEMS_COUNT)
			$count = self::ITEMS_COUNT;

		$data[$product_id][$size] = $count;
	}

	/**
	 * Добавление в корзину товара указанных размеров
	 * @param int $product_id - id товара
	 * @param Array $sizes - размеры товаров
	 * @return void
	 */
	public function add($product_id, $sizes)
	{
		$data = &$this->data;
		if ($data === false)
			return;

		if (empty($sizes))
			$sizes = array(0);

		while (list(, $size) = each($sizes))
		{
			$this->prep_size($size);
			
			if (!isset($data[$product_id]))
				$data[$product_id] = array();

			if (!isset($data[$product_id][$size]))
				$data[$product_id][$size] = 0;

			if ($data[$product_id][$size] == self::ITEMS_COUNT)
				return;

			$data[$product_id][$size] = $data[$product_id][$size] + 1;
		}
	}

	public function delete($product_id, $size = 0)
	{
		$data = &$this->data;
		if ($data === false)
			return;

		$this->prep_size($size);

		if (!isset($data[$product_id]))
			return;
		if (!isset($data[$product_id][$size]))
			return;
		unset($data[$product_id][$size]);

		if (empty($data[$product_id]))
			unset($data[$product_id]);
	}

	public function change_size($product_id, $old_size, $new_size)
	{
		if ($old_size == $new_size)
			return;

		$data = &$this->data;
		if ($data === false)
			return;

		$this->prep_size($old_size);
		$this->prep_size($new_size);

		if (!isset($data[$product_id]))
			return;

		if (!isset($data[$product_id][$old_size]))
			return;

		$count = $data[$product_id][$old_size];

		$data[$product_id][$new_size] = $data[$product_id][$old_size];
		unset($data[$product_id][$old_size]);
	}

	public function is_empty()
	{
		$data = &$this->data;
		if ($data === false)
			return;

		return empty($data);
	}

	public function clear()
	{
		$data = &$this->data;
		if ($data === false)
			return;

		$keys = array_keys($data);

		while (list(, $key) = each($keys))
			unset($data[$key]);
	}

	private function prep_size(&$size)
	{
		$size = trim($size);
		if (empty($size))
			$size = 0;

		$size = (float)$size;
		$size = (String)$size;
	}

	/**
	 * Получение общих данных корзины: кол-во товаров, общая стоимость, исходная исх. стоимость
	 * @return Array - данные корзины
	 */
	public function summary()
	{
		$data = &$this->data;
		if ($data === false)
			return;

		if ($data === false)
			return false;

		$price = 0;
		$price_initial = 0;
		$summ_count = 0;
		$products = array();
		reset($data);
		while (list($product_id, $sizes) = each($data))
		{
			$product = $this->objects->Products->get($product_id);
			if ($product === false)
				continue;

			while (list($size, $count) = each($sizes))
			{
				$price += $count * $product['price'];
				$price_initial += $count * $product['initial_price'];
				$summ_count += $count;

				$product['size'] = $size;
				$product['count'] = $count;
				$products[] = $product;
			}
		}

		return array('products' => $products, 'price' => $price, 'initial_price' => $price_initial, 'count' => $summ_count);
	}

	/**
	 * Сохранение данных корзины
	 * @param Boolean $merge - флаг определяющий соединять с данными которые уже записаны в БД или нет
	 */
	public function save($merge = false)
	{
		if (!$this->objects->Accounts->is_authed())
			return;

		$account_info = $this->objects->Accounts->get();
		$db_data = unserialize($account_info['data']);

		$data = &$this->data;
		if ($data === false)
			return;

		if ($merge === true)
		{
			while (list($product_id, $sizes) = each($db_data))
			{
				while (list($size, $count) = each($sizes))
				{
					if (!isset($data[$product_id]))
						$data[$product_id] = array();

					if (!isset($data[$product_id][$size]))
						$data[$product_id][$size] = 0;

					$data[$product_id][$size] += $count;
				}
			}
		}

		$this->objects->Accounts->update_user_data($_SESSION['id'], array('data' =>  serialize($data)));
	}

	/**
	 * Получение форматированного списка товаров корзины
	 * @param Array  $data - данные корзины
	 * @param Template $template - шаблон для элементов товаров
	 * @return String - список товаров
	 */
	public function format_list($template)
	{
		$data = &$this->data;
		if ($data === false)
			return;

		$product_list = "";
		reset($data);
		while (list($product_id, $sizes) = each($data))
		{
			$product = $this->objects->Products->get($product_id);
			if ($product === false)
				continue;

			$child_category = $this->objects->Categories->get($product['child_id']);
			$parent_category = $this->objects->Categories->get($product['parent_id']);
		
			while (list($size, $count) = each($sizes))
			{
				$template->clear();
				$product['url_name'] = str_replace(" ", "+", $product['name']);
				$product['count'] = $count;
				$product['size_'.$size] = $size;
				$product['image_small'] = $this->Products->get_image_small($product['id']);
				$template->bind_params($product);

				if ($parent_category !== false)
					$template->bind_params($parent_category->get_data(), "Parent_category::");
				if ($child_category !== false)
					$template->bind_params($child_category->get_data(), "Child_category::");

				$caregory_parent = $this->Categories->get($product['parent_id']);
				$template->{$caregory_parent->name} = true;

				$product_list .= (string) $template;
			}
		}

		return $product_list;
	}

	/**
	 * Биндинг к товарам флага в корзине он или нет
	 * @param Template $template - шаблон элемента товара для биндинга
	 * @param Integer $product_id - id товара
	 */
	public function bind_product($template, $product_id)
	{
		$data = &$this->data;
		if ($data === false)
			return;

		if (!isset($data[$product_id]))
			return;

		$template->basket = true;
	}

	/**
	 * Выдача общей информации корзины в виде xml
	 */
	public function send_xml_basket()
	{
		$basket = $this->summary();
		if ($basket === false)
			$this->objects->XML->send_empty();

		unset($basket['products']);
		$xml = $this->objects->XML->start_answer();
		$basket_node = $xml->addChild("basket");
		$this->objects->XML->write_attributes($basket_node, $basket);
		$this->objects->XML->send_xml($xml);
	}
}

?>