<?php

/**
 * Реализует операции пользователя с корзиной
 * @uses ObjectBasket
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @uses ObjectXML
 * @version 1.0.0
 */
class UserBasket extends ComponentUser
{
	public function on_add($data)
	{
		$fields = array('id'	=> array('filter' => FILTER_VALIDATE_INT),
				'sizes'	=> array('array' => true, 'require' => false)
			);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			$this->XML->send_empty();

		$this->Basket->add($fields['id'], $fields['sizes']);
		$this->Basket->save();

		$this->Basket->send_xml_basket();
	}

	/**
	 * Установка кол-ва для изделия в корзине
	 */
	public function on_set_count($data)
	{
		$fields = array(
			'id'		=> array('filter' => FILTER_VALIDATE_INT),
			'count'		=> array('filter' => FILTER_VALIDATE_INT),
			'size'		=> array('require' => false, 'filter' => FILTER_VALIDATE_FLOAT)
		);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			$this->XML->send_empty();

		$this->Basket->set($fields['id'], $fields['count'],  $fields['size']);
		$this->Basket->save();

		$this->Basket->send_xml_basket();
	}

	/**
	 * Изменение размера изделия
	 */
	public function on_set_size($data)
	{
		$fields = array(
			'id'		=> array('filter' => FILTER_VALIDATE_INT),
			'new_size'	=> array('filter' => FILTER_VALIDATE_FLOAT),
			'old_size'	=> array('require' => false, 'filter' => FILTER_VALIDATE_FLOAT)

		);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			exit;

		$this->Basket->change_size($fields['id'], $fields['old_size'], $fields['new_size']);
		$this->Basket->save();
		exit;
	}

	/**
	 *  Удаление товара из корзины
	 */
	public function on_delete($data)
	{
		$fields = array(	'id'	=> array('filter' => FILTER_VALIDATE_INT),
					'size'	=> array('require' => false, 'filter' => FILTER_VALIDATE_FLOAT)
			);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			$this->XML->send_empty();

		$this->Basket->delete($fields['id'], $fields['size']);
		$this->Basket->save();

		$this->Basket->send_xml_basket();
	}

	/*
	 * Биндинг данных корзины на страницы (общей суммы, кол-во товаров)
	 */
	public function on_bind_data($data)
	{
		$count = $this->Products->count();
		$this->Templates->products_count = number_format($count, 0, '.', ' ');

		$basket = $this->Basket->summary();
		if ($basket === false)
			return "nopage";

		unset($basket['products']);
		$this->Templates->bind_params($basket, "Basket::");

		return "nopage";
	}

	/**
	 * Отображение содержания корзины
	*/
	public function on_get($data)
	{
		$account = false;
		if ($this->Accounts->is_authed())
			$account = $this->Accounts->get();


		if (!$this->EasyForms->field("send"))
		{
			$this->set_items();

			if ($account === false)
				return;

			$name = $account['name'];
			if (!empty($account['lastname']))
				$name .= " ".$account['lastname'];
			if (!empty($account['patronymic']))
				$name .= " ".$account['patronymic'];

			$account['name'] = $name;
			$this->Templates->bind_params($account, "field_");

			return;
		}

		$params = $data['params'];
		$this->check_params($params, array("email"));
		if (empty($params['email']))
			$this->Log->error("not exist param email");

		$fields = array('name'		=> array(),
				'phone'		=> array(),
				'email'		=> array(),
				'address'	=> array(),
				'comment'	=> array()
		    );
		$fields = $this->EasyForms->fields($fields, array('require' => false));

		$basket = $this->Basket->summary();
		$this->Templates->price = $basket['price'];
		$this->Templates->bind_params($fields, "field_");

		$fields['phone'] = trim($fields['phone']);
		$fields['email'] = trim($fields['email']);
		if (empty($fields['phone']) && empty($fields['email']))
		{
			$this->Errors->add("simple", array('phone' => "Необходимо указать телефон или email"));
			$this->set_items();
			return;
		}

		if ($basket['price'] < 1000)
		{
			$this->Errors->add(array('price'), "Минимальная сумма заказа 1000 рублей");
			$this->set_items();
			return;
		}

		$template = $this->Templates->get("/Страницы/Шаблоны/Письма/Заказ/Обычный");
		$product_item = $template->product_item;
		if ($product_item === false)
			$this->Log->error("Can't find 'product_item' param");

		$basket = $this->Basket->summary();
		$products = $basket['products'];
		$fields['price'] = $basket['price'];
		reset($products);
		$products_list = "";
		while (list(, $product) = each($products))
		{
			$product['image_small'] = $this->Products->get_image_small($product['id']);
			$product_item->bind_params($product);
			$products_list .= (string) $product_item;
		}

		$fields['account_id'] = $account['id'];
		$order_id = $this->Orders->add_order($fields);

		$this->Orders->add_order_items($order_id, $products);

		$fields['orders'] = $products_list;
		$fields['id'] = $order_id;

		if (!empty($fields['email']))
			$this->Mail->send($fields['email'], "Шаблоны/Письма/Заказ/Обычный", $fields);
		else if (!$this->Accounts->is_authed() && isset($_SESSION['email']))
			$this->Mail->send($_SESSION['email'], "Шаблоны/Письма/Заказ/Обычный", $fields);

		$this->Mail->send(MAIL_SUPPORT, "Шаблоны/Письма/Заказ/Обычный_техническое", $fields);

		$this->Basket->clear();

		$this->Templates->account = $this->Accounts->is_authed();

		Component::redirect("Заказ", array('id' => $order_id));
	}

	private function set_items()
	{
		$basket_item = $this->Templates->basket_item;
		if ($basket_item === false)
			$this->Log->error("Can't find 'basket_item' param");

		$this->Templates->items = $this->Basket->format_list($basket_item);
	}
}

?>