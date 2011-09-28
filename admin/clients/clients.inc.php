<?php

/**
 * Модуль управления клиентами в интернет магазине
 * @uses ObjectAccounts
 * @uses ObjectCommon
 * @uses ObjectDictionaries
 * @uses ObjectEasyForms
 * @uses ObjectLog
 * @uses ObjectTemplates
 * @version 1.0.1
 */

class AdminClients extends ComponentAdmin
{
	public function initialize()
	{
		parent::initialize();

		$this->Templates->set_base_path("Панель администрирования/Клиенты");
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница AJAX");
	}

	public function get_services()
	{
		return array('index' => "Клиенты");
	}

	public function get_access_overrides()
	{
		return array('get_data' => "INDEX");
	}

	public function on_index()
	{
		$this->Templates->set_template("Панель администрирования/Шаблоны/Страница");
		$this->Templates->set_page("");

		/*$clients = $this->Accounts->get_all();
		$client_item = $this->Templates->client_item;
		if ($client_item === false)
			$this->Log->error("Can't find 'client_item' param");

		$clients_list = "";
		while (($row = $clients->fetch()))
		{
			$client_item->clear();
			$row['month_birth'] = str_pad($row['month_birth'], 2, "0", STR_PAD_LEFT);
			$client_item->bind_params($row);

			$clients_list .= (string) $client_item;
		}

		$this->Templates->clients = $clients_list;*/
	}

	/**
	 * Отправляет данные о пользователях в JSON формате
	 */
	public function on_get_data()
	{
		$this->Accounts->send_data();
	}

	public function on_delete()
	{
		$fields = array(	'ids' => array('array' => true)
			);
		$fields = $this->EasyForms->fields($fields);

		if ($fields === false)
			exit;

		$this->Accounts->delete_all($fields['ids']);
		exit;
	}

	public function on_edit()
	{
		$this->Templates->set_page("Редактирование");
		if (!$this->EasyForms->field("send"))
		{
			$client_id = $this->EasyForms->field("id", INPUT_GET);
			$client_id = intval($client_id);
			if (empty($client_id))
				return;

			$client = $this->Accounts->get($client_id);
			if ($client === false)
				return;

			$this->Templates->bind_params($client);
			return;
		}

		$fields = array('id'		=> array('require' => true),
				'name'		=> array(),
				'phone'		=> array(),
				'day_birth'	=> array(),
				'month_birth'	=> array(),
				'year_birth'	=> array(),
				'lastname'	=> array(),
				'patronymic'	=> array(),
				'email'		=> array(),
				'card'		=> array());
		$fields = $this->EasyForms->fields($fields, array('type' => INPUT_POST, 'require' => false));
		$this->Templates->bind_params($fields);
		$client_id = $fields['id'];
		unset($fields['id']);
		$email = $fields['email'];
		unset($fields['email']);
		$this->Accounts->update_user($client_id, array('email' => $email));
		$this->Accounts->update_user_data($client_id, $fields);
		Component::redirect("admin", array('module' => "clients", 'action' => "index"));
	}
}

?>
