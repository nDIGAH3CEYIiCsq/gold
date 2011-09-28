<?php

/**
 * Реализует операции с заказами в интернет магазине
 * @uses ObjectBasket
 * @uses ObjectLog
 * @uses ObjectMail
 * @uses ObjectOrders
 * @uses ObjectTemplates
 * @version 1.0.0
 */
class ObjectOrders extends Object implements DatabaseInterface
{
	static private $deliveries = array(	'courier'	=> "Курьерская доставка",
						'post'		=> "Доставка почтой"
		);

	static private $pays = array(		'cash'		=> "наличные",
						'bank'		=> "банковский перевод",
						'webmoney'	=> "webmoney",
						'yandex'	=> "yandex.деньги"
		);

	static private $statuses = array(
		'accept'	=> "ожидает начала обработки",
		'process'	=> "на обработке",
		'courier'	=> "готов к исполнению курьером",
		'end'		=> "выполнен",
		'cancel'	=> "aннулирован",
		'return'	=> "возврат"
		);

	static public function get_queries()
	{
		return array(
			'add_order'			=> "INSERT INTO @porders SET @a",
			'add_item'			=> "INSERT INTO @porder_items SET @a",

			'delete'			=> "DELETE FROM @porders WHERE id = @i",
			'delete_item'			=> "DELETE FROM @porder_items WHERE id = @i",
			'delete_items'			=> "DELETE FROM @porder_items WHERE order_id = @i",

			'get'				=> "SELECT * FROM @porders WHERE id = @i",
			'get_items'			=> "SELECT * FROM @porder_items WHERE order_id = @i ORDER BY add_time DESC ",
			'get_item'			=> "SELECT * FROM @porder_items WHERE id = @i",

			'get_not_end'			=> "SELECT * FROM @porders WHERE status != 'end' and status != 'cancel' @A ORDER BY add_time DESC",
			'get_end'			=> "SELECT * FROM @porders WHERE (status = 'end' OR status = 'cancel') @A ORDER BY add_time DESC",

			'update'			=> "UPDATE @porders SET @a WHERE id = @i",
			'update_price'			=> "UPDATE @porders SET price = price + @f WHERE id = @i",

			'update_item'			=> "UPDATE @porder_items SET @a WHERE id = @i",

			'get_orders'			=> "SELECT id, id, CONCAT('<div>', name, '</div><div>', phone, '</div><div>', email, '</div><div>', address, '</div>') as buyer, add_time, price, payment_method, delivery_method, delivery_price, status, id FROM @porders WHERE add_time > @i AND add_time <= @i @A @A @O @L",
			'get_orders_count'		=> "SELECT count(*) as total FROM @porders WHERE add_time > @i AND add_time <= @i @A @A",

			'get_courier_orders'		=> "SELECT id, id, CONCAT('<div>', name, '</div><div>', phone, '</div><div>', email, '</div><div>', address, '</div>') as buyer, add_time, price, payment_method, delivery_method, delivery_price, status, id FROM @porders WHERE (status = 'courier' OR status='end') AND add_time > @i AND add_time <= @i @A @O @L",
			'get_courier_orders_count'	=> "SELECT count(*) as total FROM @porders WHERE (status = 'courier' OR status='end') AND add_time > @i AND add_time <= @i @A",

			'get_all'			=> "SELECT * FROM @porders ORDER BY ID DESC"
			);
	}

	public function get($id)
	{
		$result = $this->DB->get($id);
		if ($result->is_empty())
			return false;

		return $result->fetch();
	}

	public function get_items($order_id)
	{
		return $this->DB->get_items($order_id);
	}

	public function get_item($id)
	{
		$result = $this->DB->get_item($id);
		$row = $result->fetch();
		return $row;
	}

	public function send_orders($data)
	{
		$time = $this->get_time_limited($data);

		if ($data['status'] == "all")
			$status = array();
		else
			$status = array('status' => $data['status']);

		$table = array(
			'fields'	=> array("id", "id", "buyer", "add_time", "price", "payment_method", "delivery_method", "delivery_price", "status", "id"),
			'count'		=> array(&$this->DB, "get_orders_count"),
			'data'		=> array(&$this->DB, "get_orders"),
			'params'	=> array($time['min'], $time['max'], $status)
		);

		$this->Tables->send($table);
	}

	public function send_courier_orders($data)
	{
		$time = $this->get_time_limited($data);

		$table = array(
			'fields'	=> array("id", "id", "buyer", "add_type", "price", "payment_method", "delivery_method", "delivery_price", "status", "id"),
			'count'		=> array(&$this->DB, "get_courier_orders_count"),
			'data'		=> array(&$this->DB, "get_courier_orders"),
			'params'	=> array($time['min'], $time['max'])
		);

		$this->Tables->send($table);
	}

	/*
	 * Удаление заказа
	 */
	public function delete($id)
	{
		$this->DB->delete($id);
		$this->DB->delete_items($id);
	}

	public function edit_order($id, $data)
	{
		$this->DB->update($data, $id);
	}

	public function delete_order_item($id)
	{
		$result = $this->DB->get_item($id);
		$row = $result->fetch();
		if ($row === false)
			return;

		$this->DB->delete_item($row['id']);

		$this->update_price($row['order_id'], - ($row['price'] * $row['count']));
	}

	/*
	 * Получение не обработанных заказов
	 */
	public function get_not_end($account_id = false)
	{
		if ($account_id === false)
			return $this->DB->get_not_end();
		
		return $this->DB->get_not_end(array('account_id' => $account_id));
	}

	/*
	 * Получение обработанных заказов
	 */
	public function get_end($account_id = false)
	{
		if ($account_id === false)
			return $this->DB->get_end();

		return $this->DB->get_end(array('account_id' => $account_id));
	}

	/*
	 * Добавление заказа
	 */
	public function add_order($data)
	{
		$data['add_time'] = time();
		$this->DB->add_order($data);
		$buyer_id = $this->DB->insert_id;
		return $buyer_id;
	}

	/*
	 * Добавление заказа
	 */
	public function add_order_items($order_id, $products)
	{
		reset($products);

		$add_ids = array();
		while(list(, $product) = each($products))
		{
			if (!(isset($product['status_manager'])))
				$product['status_manager'] = "ignored";

			$this->DB->add_item(array(	'order_id'		=> $order_id,
							'code'			=> $product['code'],
							'price'			=> $product['price'],
							'initial_price'		=> $product['initial_price'],
							'name'			=> $product['name'],
							'size'			=> $product['size'],
							'count'			=> $product['count'],
							'status_manager'	=> $product['status_manager'],
							'add_time'		=> time()
				));
			$add_ids[] = $this->DB->insert_id;
		}
		return $add_ids;
	}

	public function update_item($id, $data)
	{
		$this->DB->update_item($data, $id);
	}

	public function update_price($order_id, $diff_price)
	{
		$this->DB->update_price($diff_price, $order_id);
	}

	/*
	 * Установка данных статуса, доставки и оплаты заказа
	 */
	public function parse_data(&$order)
	{
		if (isset(self::$statuses[$order['status']]) && !empty($order['status']))
			$order['status'] = self::$statuses[$order['status']];
		if (!empty($order['delivery']))
			$order['delivery'] = self::$deliveries[$order['delivery']];
		if (!empty($order['pay']))
			$order['pay'] = self::$pays[$order['pay']];
	}

	public function get_deliveries()
	{
		return self::$deliveries;
	}

	public function get_pays()
	{
		return self::$pays;
	}

	public function get_statuses()
	{
		return self::$statuses;
	}

	private function get_time_limited($data)
	{
		if (!empty($data['period']))
		{
			switch($data['period'])
			{
				case "today":
					$min_time = strtotime(date("Y-m-d"));
					$max_time = strtotime("now");
					break;
				case "yesterday":
					$min_time = strtotime(date("Y-m-d", strtotime("yesterday")));
					$max_time = strtotime(date("Y-m-d"));
					break;
				case "current_week":
					$min_time = strtotime(date("Y-m-d", strtotime("-7 days")));
					$max_time = strtotime("now");
					break;
				case "current_month":
					$min_time = strtotime(date("Y-m-d", strtotime("-1 month")));
					$max_time = strtotime("now");
					break;
				case "current_year":
					$first_day_of_current_year = "01.01.".date("Y");
					$min_time = strtotime($first_day_of_current_year);
					$max_time = strtotime("now");
					break;
				case "last_year":
					$first_day_of_last_year = "01.01.".date("Y", strtotime("-1 year"));
					$min_time = strtotime($first_day_of_last_year);
					$first_day_of_current_year = "01.01.".date("Y");
					$max_time = strtotime($first_day_of_current_year);
					break;
			}
		}
		if (!empty($data['month']) && !empty($data['year']))
		{
			$min_time = strtotime("01.".$data['month'].".".$data['year']);
			$max_time = strtotime("+1 month", $min_time);
		}


		return array('min' => $min_time, 'max' => $max_time);
	}
}

?>
