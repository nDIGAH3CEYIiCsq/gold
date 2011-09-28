<?php
/**
 * Реализует отображение заказов и операции с ними клиентов
 * @uses ObjectAccounts
 * @uses ObjectBasket
 * @uses ObjectEasyForms
 * @uses ObjectErrors
 * @uses ObjectHandlers
 * @uses ObjectLog
 * @uses ObjectMail
 * @uses ObjectOrders
 * @uses ObjectProducts
 * @uses ObjectTemplates
 * @uses ObjectXML
 * @version 1.0.1
 */
class UserOrders extends ComponentUser
{
	/*
	 * Отображение заказов пользователя
	 */
	public function on_get($data)
	{
		if (!$this->Accounts->is_authed())
			Component::redirect("Вход");

		$this->Templates->set_page("Личный кабинет/Мои заказы");

		$data = $this->Orders->get_not_end($_SESSION['id']);
		$this->bind_orders_by_type_list($data);
	}

	/*
	 * Отображение завершенных заказов пользователя
	 */
	public function on_history($data)
	{
		if (!$this->Accounts->is_authed())
			Component::redirect("Вход");

		$this->Templates->set_page("Личный кабинет/История заказов");
		$data = $this->Orders->get_end($_SESSION['id']);
		$this->bind_orders_by_type_list($data);
	}

	public function on_success($data)
	{
		$order_id = $this->EasyForms->field("id", INPUT_GET);
		$order_id = intval($order_id);
		if (empty($order_id))
			Component::redirect ("Корзина");

		$order = $this->Orders->get($order_id);
		if ($order_id === false)
			Component::redirect ("Корзина");

		if ($order['opened'] == 1)
			Component::redirect ("Корзина");

		$this->Orders->edit_order($order_id, array('opened' => 1));
		$order['id'] = $this->format_order_number($order_id);
		$this->Templates->bind_params($order);
	}

	private function format_order_number($number)
	{
		return str_pad($number, 6, "0", STR_PAD_LEFT);
	}

	private function bind_orders_by_type_list($result)
	{
		$order_item = $this->Templates->order_item;
		if ($order_item === false)
			$this->Log->error("Can't find 'order_item' param");
		$product_item = $this->Templates->product_item;
		if ($product_item === false)
			$this->Log->error("Can't find 'product_item' param");

		$orders = array();

		while(($row = $result->fetch()))
		{
			$order_item->clear();

			$status = $row['status'];

			$this->format_order($row, $order_item, $product_item);

			if (!isset($orders[$status]))
				$orders[$status] = "";

			$orders[$status] .= $order_item;
		}

		$this->Templates->exist_data = !empty($orders);

		reset($orders);
		while (list($status, $list) = each($orders))
			$this->Templates->set_param($status."_orders",  $list);		
	}

	private function format_order($order, &$order_item = false, &$product_item = false)
	{
		$this->get_template("order_item", $order_item);
		$this->get_template("product_item", $product_item);


		if (!empty($order['pay']))
			$order_item->$order['pay'] = true;

		$this->Orders->parse_data($order);

		if (!empty($order['delivery_price']))
			$order['price'] = $order['price'] + $order['delivery_price'];

		$order_item->bind_params($order);

		$result = $this->Orders->get_items($order['id']);

		$product_list = "";
		while(($row = $result->fetch()))
		{
			$product_item->clear();
			$product_item->bind_params($row);
			$product_list .= (string) $product_item;
		}

		$order_item->products = $product_list;
	}

	private function get_template($name, $template = false)
	{
		if ($template !== false)
			return $template;

		$template = $this->Templates->$name;
		if ($template === false)
			$this->Log->error("Can't find '$name' param");

		return $template;
	}
}
?>
