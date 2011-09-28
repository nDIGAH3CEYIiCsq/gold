<?php

/**
 * Управление заказами клиентов
 * @uses ObjectBasket
 * @uses ObjectEasyForms
 * @uses ObjectLists
 * @uses ObjectLog
 * @uses ObjectOrders
 * @uses ObjectTemplates
 * @version 1.0.1
 */
class AdminOrders extends ComponentAdmin
{
	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Заказы");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница AJAX");
	}

	public function get_services()
	{
		return array('index' => "Заказы");
	}

	public function get_access_overrides()
	{
		return array(
			'item'			=> "INDEX",
                        'get_data'              => "INDEX",
			'archive'		=> "INDEX",
			'archive_item'		=> "INDEX",
			'edit_status'		=> "EDIT",
			'set_count'		=> "EDIT",
			'set_status'		=> "EDIT",
			'set_delivery'		=> "EDIT",
			'set_pay'		=> "EDIT",
			'add'			=> "EDIT",
			'submit_add'		=> "EDIT",
			'delete_products'	=> "DELETE");
	}

	public function on_index()
	{
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
		$this->Templates->set_page("");
	}

        /**
	 * Отправляет данные о пользователях в JSON формате
	 */
	public function on_get_data()
	{
		$this->Orders->send_data();
	}

	public function on_archive()
	{
		$this->Templates->set_page("Архив");
		$orders = $this->Orders->get_end();
		$this->Templates->orders = $this->format_list($orders);
	}

	public function on_delete()
	{
		$fields = array(
				'ids' => array()
		);
		$fields = $this->EasyForms->fields($fields,  array('array' => true, 'type' => INPUT_POST));

		if ($fields === false)
			exit;

		$this->Orders->Delete($fields['ids']);
		exit;
	}

	public function on_item()
	{
		$order_id = $this->EasyForms->field("id", INPUT_GET);
		$order_id = intval($order_id);
		if (empty($order_id))
			Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "orders", 'action' => "index"));

		$order = $this->Orders->get($order_id);
		if ($order === false)
			Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "orders", 'action' => "index"));

		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
		$this->Templates->set_page("Заказ");

		$this->Orders->parse_data($order);
		
		$this->bind_list_params();

		$this->Templates->bind_params($order);

		$products = unserialize($order['products']);

		$product_item = $this->Templates->product_item;
		if ($product_item === false)
			$this->Log->error("Can't find 'product_item' param");

		$items = "";
		$order['products'] = unserialize($order['products']);
		reset($order['products']);
		while (list(, $product) = each($order['products']))
		{
			$product_item->bind_params($product);
			$items .= (string) $product_item;
		}

		$this->Templates->products = $items;
	}

	public function on_archive_item()
	{
		$order_id = $this->EasyForms->field("id", INPUT_GET);
		$order_id = intval($order_id);
		if (empty($order_id))
			return;

		$order = $this->Orders->get($order_id);
		if ($order === false)
			return;

		$this->Templates->set_page("Архив/Заказ");
		$this->Orders->parse_data($order);
		$this->Templates->bind_params($order);
		$this->Templates->products = $this->format_list_products($order);
	}

	public function on_set_status()
	{
		$this->set_param("status");
	}

	public function on_set_delivery()
	{
		$this->set_param("delivery");
	}

	public function on_set_pay()
	{
		$this->set_param("pay");
	}

	public function on_set_count()
	{
		Component::print_headers();
		$fields = array(
			'id'		=> array(),
			'count'		=> array(),
			'size'		=> array(),
			'product_id'	=> array()
		);

		$fields = $this->EasyForms->fields($fields, array('flags' => FILTER_VALIDATE_INT));
		if ($fields === false)
			exit;

		$order = $this->Orders->get($fields['id']);
		if ($order === false)
			exit;

		$order['products'] = unserialize($order['products']);
		if (!isset($orders['products'][$fields['product_id']][$fields['size']]))
			exit;

		$order['products'][$fields['product_id']][$fields['size']] = $fields['count'];

		$summary = $this->Basket->summary($order['products']);
		$summary = $summary['buy'];
		$this->Orders->update($fields['id'], array('products' =>  serialize($order['products']), 'price' => $summary['price']));

		echo $this->format_list_products($order['products']);
		exit;
	}

	public function on_delete_products()
	{
		$fields = array(
			'ids'		=> array('array' => true),
			'order_id'	=> array('flags' => FILTER_VALIDATE_INT)
		);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			exit;

		$order = $this->Orders->get($fields['order_id']);
		if ($order === false)
			exit;

		$products = unserialize($order['products']);
		while (list($product_id,) = each($products))
			if (in_array($product_id, $fields['ids']))
				unset($products[$product_id]);

		$summary_data = $this->Basket->summary_data($products);
		$products = serialize($products);
		$this->Orders->update($fields['order_id'], array('products' => $products, 'price' => $summary_data['buy']['price']));
		exit;
	}

	public function on_add()
	{	
		$this->Templates->set_page("Добавление заказа");
		$this->bind_list_params();

		if (!$this->EasyForms->field("send", INPUT_POST))
			return;

		$fields = array('name'		=> array('require' => false),
				'lastname'	=> array('require' => false),
				'patronymic'	=> array('require' => false),
				'legal'		=> array(),
				'delivery'	=> array('caption' => "Способ доставки"),
				'status'	=> array(),
				'pay'		=> array('caption' => "Способ оплаты"),
				'address'	=> array('require' => false),
				'phone'		=> array('require' => false),
				'products'	=> array()
			);
		$fields = $this->EasyForms->fields($fields);

		if ($fields === false)
			return;

		$list_products = explode("&", $fields['products']);
		$products = array();
		$price = 0;
		$initial_price = 0;
		while (list(, $product) = each($list_products))
		{
			$data = explode("|", $product);
			if (count($data) < 2)
				continue;

			$product = $this->Products->get_by_code($data[0]);
			if ($product === false)
				continue;

			$product['count'] = $data[1];
			if (count($data) == 3)
				$product['size'] = $data[2];

			$products[] = $product;
			$price += $product['price'] * $data[1];
			$initial_price += $product['initial_price'] * $data[1];
		}
		$fields['products'] = $products;
		$fields['price'] = $price;
		$fields['initial_price'] = $initial_price;
		$this->Orders->add($fields, false);

		Component::redirect($_SERVER['SCRIPT_NAME'], array('module' => "orders", 'action' => "index"));
	}

	private function format_list($result)
	{
		$order_item = $this->Templates->order_item;
		if ($order_item === false)
			$this->Log->error("Can't find 'order_item' param");

		$order_list = "";
		while(($row = $result->fetch()))
		{
			$this->Orders->parse_data($row);
			$order_item->bind_params($row);

			$order_list .= (string)$order_item;
		}

		return  $order_list;
	}

	private function set_param($name)
	{
		$fields = array(
			'id'		=> array('filter' => FILTER_VALIDATE_INT),
			'new_value'	=> array()
		);
		$fields = $this->EasyForms->fields($fields);
		
		if ($fields === false)
			exit;

		$this->Orders->{"set_$name"}($fields['id'], $fields['new_value']);
		exit;
	}

	private function format_list_products($order)
	{
		$products = unserialize($order['products']);

		$product_item = $this->Templates->product_item;
		if ($product_item === false)
			$this->Log->error("Can't find 'product_item' param");

		$list_products = "";
		while (list(, $product) = each($products))
		{
			$product_item->clear();
			$product_item->bind_params($product);
			$list_products .= (string) $product_item;
		}

		return $list_products;
	}

	private function bind_list_params($order = false)
	{
		$select_item = $this->Templates->select_item;
		if ($select_item === false)
			$this->Log->error("Can't find 'select_item' param");

		$this->Templates->statuses = $this->Lists->make($select_item, $this->Orders->get_statuses(), $order !== false? $order['status']:false);
		$this->Templates->deliveries = $this->Lists->make($select_item, $this->Orders->get_deliveries(), $order !== false? $order['delivery']:false);
		$this->Templates->pays = $this->Lists->make($select_item, $this->Orders->get_pays(), $order !== false? $order['pay']:false);

	}
}

?>