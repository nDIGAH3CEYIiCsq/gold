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
class AdminManagerReport extends ComponentAdmin
{
	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Отчет менеджера");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
	}

	public function get_services()
	{
		return array('index' => "Отчет менеджера");
	}

	public function get_access_overrides()
	{
		return array(	'add_buyer'		=> "EDIT",
				'edit_order'		=> "EDIT",
				'get_orders'		=> "INDEX",
				'delete'		=> "EDIT",
				'get_order_items'	=> "INDEX",
				'delete_item'		=> "EDIT",
				'add_item'		=> "EDIT",
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

		$this->Orders->send_orders($fields);
	}

	public function on_add_buyer()
	{
		$this->XML->start_xml("errors");

		$fields = array(
			'name'			=> array(),
			'phone'			=> array(),
			'email'			=> array(),
			'address'		=> array(),
			'payment_method'	=> array(),
			'delivery_method'	=> array(),
			'delivery_price'	=> array()
		);
		$fields = $this->EasyForms->fields($fields, array('require' => false));

		if (empty($fields['phone']) && empty($fields['email']))
			$this->Errors->add("simple", array('add_buyer' => "Заполните email или телефон"));

		$this->Orders->add_order($fields);
	}

	public function on_edit_order()
	{
		$fields = array('id'			=> array(),
				'price'			=> array('filter' => FILTER_VALIDATE_INT),
				'payment_method'	=> array('require' => false),
				'delivery_method'	=> array('require' => false),
				'delivery_price'	=> array('require' => false, 'filter' => FILTER_VALIDATE_INT),
				'status'		=> array('require' => false),
				'name'			=> array(),
				'phone'			=> array('require' => false),
				'email'			=> array('require' => false),
				'address'		=> array('require' => false)
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
				'code'			=> array(),
				'name'			=> array('require' => false),
				'initial_price'		=> array('filter' => FILTER_VALIDATE_FLOAT),
				'price'			=> array('filter' => FILTER_VALIDATE_FLOAT),
				'size'			=> array('require' => false),
				'count'			=> array('filter' => FILTER_VALIDATE_INT),
				'status_manager'	=> array()
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
		if ($fields['initial_price'] <= 0)
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

		$item[$item['status_manager']] = true;
		$item['status_manager'] = $this->get_manager_status($item['status_manager']);
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
			$row[$row['status_manager']] = true;
			$row['status_manager'] = $this->get_manager_status($row['status_manager']);
			$order_item->bind_params($row);
			$items_list .= (string) $order_item;
		}

		$order = $this->Orders->get($id);

		$xml = $this->XML->start_answer();
		$xml->addChild("items", $items_list);
		$xml->addChild("comment", $order['comment']);
		$this->XML->send_xml($xml);
	}

	public function on_delete()
	{
		$id = $this->EasyForms->field("id");
		if ($id === false)
			exit;

		$this->Orders->delete($id);
		exit;
	}

	public function on_delete_item()
	{
		$id = $this->EasyForms->field("id");
		if ($id === false)
			exit;

		$this->Orders->delete_order_item($id);
		exit;
	}

	public function on_add_item()
	{
		Component::print_headers("text/xml");

		$fields = array('order_id'		=> array('filter' => FILTER_VALIDATE_INT),
				'code'			=> array('filter' => FILTER_VALIDATE_INT),
				'size'			=> array('require' => false, 'filter' => FILTER_VALIDATE_FLOAT),
				'count'			=> array('filter' => FILTER_VALIDATE_INT),
				'name'			=> array(),
				'price'			=> array('filter' => FILTER_VALIDATE_INT),
				'initial_price'		=> array('filter' => FILTER_VALIDATE_FLOAT),
				'status_manager'	=> array()

		    );
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
		{
			$this->XML->send_error("Не верно заполнены поля");
			exit;
		}
		if ($fields['size'] < 0 || $fields['size'] > 100)
		{
			$this->XML->send_error("Не верно заполнено поле размер");
			exit;
		}
		if ($fields['count'] <= 0 || $fields['count'] > 100)
		{
			$this->XML->send_error("Не верно заполнено поле кол-во");
			exit;
		}
		if ($fields['price'] <= 0 || $fields['price'] > 1000000)
		{
			$this->XML->send_error("Не верно заполна цена продажи");
			exit;
		}
		if ($fields['initial_price'] <= 0 || $fields['initial_price'] > 1000000)
		{
			$this->XML->send_error("Не верно заполнена цена отгрузки");
			exit;
		}

		$new_id = $this->Orders->add_order_items($fields['order_id'], array($fields));
		$fields['id'] = $new_id[0];

		$this->Orders->update_price($fields['order_id'], $fields['price']  * $fields['count']);

		$template = $this->Templates->get("");

		$order_item = $template->order_item;
		if ($order_item === false)
			$this->Log->error("Can't find 'order_item' param");

		$fields[$fields['status_manager']] = true;
		$fields['status_manager'] = $this->get_manager_status($fields['status_manager']);
		$order_item->bind_params($fields);
		$fields['status_manager'] = "ignored";

		$xml = $this->XML->start_answer();
		$xml->addChild("item", (string) $order_item);
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
			case 'not_size':
				return "нет размера";
			case 'not_available':
				return "нет в наличии";
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
