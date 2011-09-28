<?php

/**
 * Работа с данными пользователей зарегистрированных в интернет магазине
 * @uses ObjectAccounts
 * @uses ObjectBasket
 * @uses ObjectCaptcha
 * @uses ObjectCommon
 * @uses ObjectEasyForms
 * @uses ObjectErrors
 * @uses ObjectMail
 * @uses ObjectTemplates
 * @uses ObjectXML
 * @version 1.0.1.1
 */

class UserAccounts extends ComponentUser
{
	public function on_edit_data($data)
	{
		if (!$this->Accounts->is_authed())
			Component::redirect("Вход");
	
		if (!$this->EasyForms->field("send"))
		{
			$account = $this->Accounts->get();
			if ($account === false)
				Component::redirect("Личный кабинет");

			$this->set_form_fields($account);
			return;
		}

		$fields = array('login'		=> array(),
				'name'		=> array(),
				'phone'		=> array('require' => false),
				'lastname'	=> array(),
				'patronymic'	=> array('require' => false),
				'sex'		=> array(),
				'email'		=> array(),
				'day_birth'	=> array('require' => false),
				'month_birth'	=> array('require' => false),
				'year_birth'	=> array('require' => false)
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		$fields = $this->Common->trim($fields);

		if (!$this->check_date_birth($fields))
		{
			$this->set_form_fields($fields);
			return;
		}
		
		if (!$this->Accounts->change_login_email($_SESSION['id'], $fields['login'], $fields['email']))
		{
			$this->set_form_fields($fields);
			return;
		}

		unset($fields['login']);
		unset($fields['email']);

		$this->Accounts->update_user_data($_SESSION['id'], $fields);
		$this->set_form_fields($fields);

		$data = $_SESSION;
		$this->prep_account($data);
		$this->Templates->success = true;
		$this->Mail->send($_SESSION['email'], "Шаблоны/Письма/Личный кабинет/Изменение персональных данных", $data);
	}

	public function on_bind($data)
	{
		if (!$this->Accounts->is_authed())
			return "nopage";

		$this->Templates->set_param("Auth::authed", true);
		$info = $this->Accounts->get();

		$data = array(
			'id'		=> $_SESSION['id'],
			'login'		=> $_SESSION['login'],
			'email'		=> $_SESSION['email'],
			'name'		=> $info['name'],
			'lastnmae'	=> $info['lastname']
		);

		$this->Templates->bind_params($data, "session_");

                return "nopage";
	}

	public function on_change_password($data)
	{
		if (!$this->Accounts->is_authed())
			Component::redirect("Вход");

		$user = $this->get_user_form();
		$code = $this->EasyForms->field("code", INPUT_GET);

		if (!$this->Accounts->check_owner($user['id']))
		{
			if (!empty($user['new_email']) || empty($user['code']) || $user['code'] != $code)
				Component::redirect("");
		}

		$this->Templates->bind_params($user);

		if (!$this->EasyForms->field("send"))
			return;

		$fields = array(
			'password'	=> array('id' => "change_password"),
			'password2'	=> array('id' => "change_password2"),
			'old_password'	=> array('id' => "old_password")
		);

		$fields = $this->EasyForms->fields($fields);

		if ($fields === false)
			return false;

		if ($fields['password'] != $fields['password2'])
		{
			$this->Errors->add(array("auth", "password"), "Пароль и подтверждение пароля не совпадают");
			return;
		}

		if (!$this->Accounts->change_password($user['id'], $fields['old_password'], $fields['password']))
			return;
		
		$this->Templates->success = true;
	}

	public function on_restore_password($data)
	{
		if (!$this->EasyForms->field("send"))
			return;

		$fields = array(
			'login' => array('id' => "restore_login", 'require' => false),
			'email' => array('id' => "restore_email", 'require' => false)
		);

		try
		{
			$fields = $this->EasyForms->fields($fields);
			if ($fields === false)
				throw new Exception();

			if (!$this->Captcha->check())
				throw new Exception();

			if (!$this->Accounts->restore_password($fields))
				throw new Exception();

			$this->Templates->success = true;
		}
		catch (Exception $e)
		{
			if ($fields !== false)
				$this->EasyForms->bind($fields);
		}
	}

	public function on_register($data)
	{
		if (!$this->EasyForms->field("send"))
			return false;

		$fields = array(
			'login'			=> array(),
			'password'		=> array(),
			'password_repeat'	=> array(),
			'email'			=> array()
		);

		try
		{
			$fields = $this->EasyForms->fields($fields);
			if ($fields === false)
				throw new Exception();

			if (!$this->Captcha->check())
				throw new Exception();

			if (!$this->check_account_info($fields))
				throw new Exception();

			unset($fields['password_repeat']);

			$account_id = $this->Accounts->register($fields);
			if ($account_id === false)
				throw new Exception();

			Component::redirect("Регистрация/Подтверждение/", array('id' => $account_id));
		}
		catch (Exception $e)
		{
			if ($fields !== false)
				$this->EasyForms->bind($fields);
		}
	}

	public function on_approve($data)
	{
		$fields = array(
			'id'	=> array(),
			'code'	=> array('require' => false),
		);

		$fields = $this->EasyForms->fields($fields, array('type' => INPUT_GET));

		if ($fields === false)
			Component::redirect("");

		$this->Templates->id = $fields['id'];

		$fields['code'] = trim($fields['code']);
		if (empty($fields['code']))
			return;

		if (!$this->Accounts->approve($fields['id'], $fields['code']))
			return;

		Component::redirect("Личный кабинет/");
	}

	public function on_reapprove($data)
	{
		$id = $this->EasyForms->field("id", INPUT_GET);
		if ($id == false)
			Component::redirect("");

		if (!$this->EasyForms->field("send"))
			return;

		if (!$this->Captcha->check())
			return;

		if (!$this->Accounts->reapprove($id))
			return;

		Component::redirect("Регистрация/Подтверждение/", array('id' => $id));
	}

	private function check_account_info($fields)
	{
		if ($fields['password'] != $fields['password_repeat'])
		{
			$this->Errors->add("simple", array('password' => "Пароль и подтверждение пароля не совпадают"));
			return false;
		}

		return true;
	}

	private function get_user_form()
	{
		$id = $this->EasyForms->field("id", INPUT_GET);

		$user = $this->Accounts->get($id);
		if ($user === false)
			Component::redirect("");

		return $user;
	}

	public function on_login($data)
	{
		if (!$this->EasyForms->field("send"))
			return;

		$fields = array(
			'login'		=> array(),
			'password'	=> array()
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		if ($this->Accounts->login($fields['login'], $fields['password']))
		{
			$anonymous_data = $this->Accounts->get_anonymous_data();
			if (isset($anonymous_data["buy"]))
			{
				$_SESSION["basket"] = $anonymous_data["basket"];
				$_SESSION['anonymous'] = array();
				
				$this->Basket->save(true);
			}
			Component::redirect("Личный кабинет/");
		}

		$this->Templates->redirect = $this->Accounts->get_redirect();
		$this->EasyForms->bind($fields);
	}

	public function on_login_ajax($data)
	{
		$fields = array(
			'login'		=> array(),
			'password'	=> array()
		);

		$fields = $this->EasyForms->fields($fields, array('require' => false));

		$xml = $this->XML->start_answer();

		if (!$this->Accounts->login($fields['login'], $fields['password']))
		{
			$xml->addChild("errors", $this->Errors->get_content());
			$xml->addChild("redirect", $this->Accounts->get_redirect());
		}
		else
		{
			$xml->addChild("result", "authed");
			$xml->addChild("login", $_SESSION['login']);
		}

		$this->XML->send_xml($xml);
	}

	public function on_logout_ajax($data)
	{
		$this->Accounts->logout();
		$this->XML->send_empty();
	}

	public function on_logout($data)
	{
		$this->Accounts->logout();
		Component::redirect("/");
	}

	public function on_change_password_ajax()
	{
		$this->XML->start_xml("errors");

		$fields = array(
			'id'		=> array(),
			'password'	=> array('id' => "change_password"),
			'password2'	=> array('id' => "change_password2")
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return;

		if (!$this->Accounts->check_owner($fields['id']))
			$this->Errors->add(array("auth", "access"), "У вас недостаточно прав для изменения пароля");
		else if ($fields['password'] != $fields['password2'])
			$this->Errors->add(array("auth", "password"), "Пароль и подтверждение пароля не совпадают");
		else if (!$this->Accounts->change_password($fields['id'], $fields['password']))
			return;

		exit;
	}

	private function set_form_fields($fields)
	{
		$_SESSION = array_merge($_SESSION, $fields);
		$fields = $_SESSION;
		$this->prep_account($fields);

		unset($fields['basket']);

		$this->Templates->bind_params($fields, "field_");
	}

	private function check_date_birth($fields)
	{
		if (!$this->check_bound("day_birth", "день", $fields, 31))
			return false;
		if (!$this->check_bound("month_birth", "месяц", $fields, 12))
			return false;
		if (!$this->check_bound("year_birth", "год", $fields, date("Y",time())))
			return false;

		return true;
	}

	private function check_bound($name1, $name2, $fields, $bound_right)
	{
		if (!empty($fields[$name1]))
		{
			$fields[$name1] = intval($fields[$name1]);
			try
			{
				if (empty($fields[$name1]))
					throw new Exception();

				if (!empty($fields[$name1]) && ($fields[$name1] < 0 || $fields[$name1] > $bound_right))
					throw new Exception();
			}
			catch(Exception $e)
			{
				$this->Errors->add("simple", array($name1 => "Не корретный $name2 рождения"));
				return false;
			}
		}
		return true;
	}

	private function prep_account(&$account)
	{
		unset($account['data']);
		unset($account['anonymous']);
		unset($account['buy']);
	}
}

?>