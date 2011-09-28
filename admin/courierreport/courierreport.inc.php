<?php
/**
 * Модуль отчетности менеджера
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @uses ObjectProducts
 * @uses ObjectCategories
 * @version 1.0.1
 */
class AdminCourierReport extends ComponentAdmin
{
	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Отчет курьера");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Отчет курьера");
	}

	public function get_access_overrides()
	{
		return array(	'edit_order'		=> "EDIT",
				'get_orders'		=> "INDEX",
				'get_order_items'	=> "INDEX",
				'edit_item'		=> "EDIT"
		);
	}

	public function on_index()
	{
		$this->Templates->set_page("");
	}

	public function on_get_orders()
	{
		$fields = array('period'	=> array('require' => false, 'type' => INPUT_GET),
				'month'		=> array('require' => false, 'type' => INPUT_GET),
				'year'		=> array('require' => false, 'type' => INPUT_GET),
				'status'	=> array('type' => INPUT_GET)
		    );
		$fields = $this->EasyForms->fields($fields);

		$this->Orders->send_courier_orders($fields);
	}

	public function on_edit_order()
	{
		$fields = array('id'			=> array(),
				'name'			=> array(),
				'address'		=> array(),
				'payment_method'	=> array(),
				'delivery_method'	=> array(),
				'status'		=> array(),
				'delivery_price'	=> array('require' => false, 'filter' => FILTER_VALIDATE_INT),
				'phone'			=> array('require' => false),
				'email'			=> array('require' => false),
			);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
		{
			$this->XML->send_error("Не верно заполнены поля");
			exit;
		}
		if ($fields['delivery_price'] < 0)
		{
			$this->XML->send_error("Не верно указана сумма доставки");
			exit;
		}
		$id = $fields['id'];
		unset($fields['id']);
		$this->Orders->edit_order($id, $fields);
		exit;
	}

	public function on_edit_item()
	{
		$fields = array('id'			=> array('filter' => FILTER_VALIDATE_INT),
				'code'			=> array('filter' => FILTER_VALIDATE_INT),
				'name'			=> array(),
				'price_base'		=> array('filter' => FILTER_VALIDATE_FLOAT),
				'price'			=> array('filter' => FILTER_VALIDATE_FLOAT),
				'count'			=> array('filter' => FILTER_VALIDATE_INT),
				'status_courier'	=> array(),
				'size'			=> array('require' => false, 'filter' => FILTER_VALIDATE_INT),
			);
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
		{
			$this->XML->send_error("Не верно заполнены поля");
			exit;
		}
		if ($fields['price'] <= 0)
		{
			$this->XML->send_error("Не верно указана цена");
			exit;
		}
		if ($fields['price_base'] <= 0)
		{
			$this->XML->send_error("Не верно указана базовая цена");
			exit;
		}
		if (($fields['size'] < 0) || ($fields['size'] > 100))
		{
			$this->XML->send_error("Не верно указан размер");
			exit;
		}
		if (($fields['count'] <= 0) || ($fields['count'] > 100))
		{
			$this->XML->send_error("Не верно указано кол-во");
			exit;
		}

		$id = $fields['id'];
		unset($fields['id']);
		$this->Orders->update_item($id, $fields);
		$item = $this->Orders->get_item($id);
		if ($item === false)
		{
			$this->XML->send_error("Не существует запись");
			exit;
		}

		$template = $this->Templates->get("");

		$order_item = $template->order_item;
		if ($order_item === false)
			$this->Log->error("Can't find 'order_item' param");

		$item[$item['status_courier']] = true;
		$item['status_courier'] = $this->get_manager_status($item['status_courier']);
		$order_item->bind_params($item);
		$xml = $this->XML->start_answer();
		$xml->addChild("item", (string) $order_item);
		$this->XML->send_xml($xml);
	}

	public function on_get_order_items()
	{
		$id = $this->EasyForms->field("id");
		if ($id === false)
			exit;

		$template = $this->Templates->get("");

		$order_item = $template->order_item;
		if ($order_item === false)
			$this->Log->error("Can't find 'order_item' param");

		$result = $this->Orders->get_items($id);
		$items_list = "";
		while (($row = $result->fetch()))
		{
			$order_item->clear();
			$row[$row['status_courier']] = true;
			$row['status_courier'] = $this->get_manager_status($row['status_courier']);
			$order_item->bind_params($row);
			$items_list .= (string) $order_item;
		}

		$order = $this->Orders->get($id);

		$xml = $this->XML->start_answer();
		$xml->addChild("items", $items_list);
		$xml->addChild("comment", $order['comment']);
		$this->XML->send_xml($xml);
	}

	private function get_manager_status($status)
	{
		switch($status)
		{
			case 'ignored':
				return "не рассмотрено";
			case 'process':
				return "в обработке";
			case 'cancel':
				return "отменен";
			case 'return':
				return "возврат";
			case 'end':
				return "выполнен";
		}
	}
}
?>
