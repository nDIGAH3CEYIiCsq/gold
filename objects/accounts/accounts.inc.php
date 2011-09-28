<?php

/**
 * Предоставляет функции регистрации и авторизации пользователей в пользовательской зоне
 *
 * @uses DatabaseInterface
 * @uses ObjectCommon
 * @uses ObjectErrors
 * @uses ObjectMail
 * @uses ObjectSessions
 *
 * @version 1.0.3.1
 */
class ObjectAccounts extends ObjectSessions implements DatabaseInterface
{
	const ACCESS_APPROVED		= 0x00000001;

	const ACCESS_ADMIN		= 0x40000000;

	const ACCESS_DEFAULT		= 0x00000000;

	private $redirect;

	static public function get_queries()
	{
		return array(
			'delete'	=> "DELETE FROM @paccounts WHERE id = @i",
			'delete_in'	=> "DELETE FROM @paccounts WHERE id in (@l)",

			'get'		=> "SELECT * FROM @paccounts b LEFT JOIN @paccounts_info d ON d.id = b.id @W",
			'get_simple'	=> "SELECT * FROM @paccounts @W",

			'add'		=> "INSERT INTO @paccounts SET @a",
			'add_data'	=> "INSERT INTO @paccounts_info SET @a",

			'update'	=> "UPDATE @paccounts SET @a WHERE id = @i",
			'update_data'	=> "UPDATE @paccounts_info SET @a WHERE id = @i",

			'get_data'	=> "SELECT a.id as id,email,reg_time,last_login,phone,concat(lastname,' ',name,' ',patronymic) as name, sex, concat(day_birth,'.',month_birth,'.',year_birth) as birth,access FROM @paccounts a LEFT JOIN @paccounts_info i on a.id=i.id @@WL @O @L",
			'get_data_count'=> "SELECT count(*) as total FROM @paccounts @@W",
		);
	}

	public function register($data)
	{
		$data['login'] = trim($data['login']);
		$data['email'] = trim($data['email']);

		if (!($this->check_login($data['login']) & $this->check_password($data['password']) & $this->check_email($data['email'])))
			return false;

		$row = $this->get_simple_by("login", $data['login']);
		if ($row !== false)
		{
			$this->Errors->add(array("auth", "register"), "Пользователь с таким логином уже существует");
			return false;
		}

		$data['code']		= $this->Common->gen_confirm_code();
		$data['reg_time']	= time();

		$fields = $this->Common->copy_fields($data, array("login", "password", "email", "code", "reg_time"));
		$fields['password']	= $this->encrypt($data['password']);

		$this->DB->add($fields);

		$data['id'] = $this->DB->insert_id;

		$fields = $this->Common->copy_fields($data, array("id", "name", "surname", "patronymic", "bday", "bmonth", "byear", "sex", "country", "city", "work"));

		$this->DB->add_data($fields);

		$this->Mail->send($data['email'], "Шаблоны/Письма/Регистрация/Информация", $data);

		return $data['id'];
	}

	public function unregister($id)
	{
		$this->DB->delete($id);

		if ($this->DB->affected_rows != 0)
		{
			$this->Errors->add(array("auth", "unregister"), "Пользователя с таким id не существует");
			return false;
		}

		$this->request_update($id);
		return true;
	}

	public function change_password($id, $old_password, $new_password)
	{
		if (!$this->check_password($new_password))
			return false;

		$user = $this->get_simple_by("id", $id);
		if ($user === false)
		{
			$this->Errors->add(array("auth", "change_password"), "Пользователь с данным ID не существует");
			return false;
		}

		$old_passwor_encrypted = $this->encrypt($old_password);
		if ($user['password'] != $old_passwor_encrypted)
		{
			$this->Errors->add(array("auth", "change_password"), "Не верно указан старый пароль");
			return false;
		}
		
		$password_encrypted = $this->encrypt($new_password);
		
		$this->update_user($id, array('password' => $password_encrypted, 'code' => ""));
		$user['password'] = $new_password;
		$this->Mail->send($user['email'], "Шаблоны/Письма/Изменение пароля", $user);

		return true;
	}

	public function delete_all($ids)
	{
		if (!is_array($ids))
			$ids = array($ids);
		
		$this->DB->delete_in($ids);
		$this->DB->delete_info($ids);
	}

	public function change_login_email($id, $login, $email)
	{
		if ($_SESSION['email'] != $email)
		{
			if (!$this->check_email($email))
				return false;
			
			$row = $this->get_simple_by("email", $email);
			if ($row !== false)
			{
				$this->Errors->add(array("auth", "email"), "Пользователь с данным Email уже существует");
				return false;	
			}
		}
		
		if ($_SESSION['login'] != $login)
		{
			$row = $this->get_simple_by("login", $login);
			if ($row !== false)
			{
				$this->Errors->add(array("auth", "login"), "Пользователь с таким логином уже существует");
				return false;
			}
		}
		$this->update_user($id, array('login' => $login, 'email' => $email));
		$row = $this->get_simple_by("id", $id);
		$this->set_login_data($row);
		
		return true;
	}


	public function get_by($type, $value)
	{
		$result = $this->DB->get(array("b.".$type => $value));
		if ($result->is_empty())
			return false;

		return $result->fetch();
	}

	public function check_owner($id)
	{
		if (parent::check_owner($id))
			return true;

		if ($this->check_access(self::ACCESS_ADMIN))
			return true;

		return false;
	}

	/**
	 * Обновляет данные аккаунта
	 * @param $id Integer: Идентификатор аккаунта
	 * @param $data Array: Изменённые данные аккаунта
	 */
	public function update_user($id, $data)
	{
		$this->DB->update($data, $id);
		$this->request_update($id);
	}

	/**
	 * Изменяет email аккаунта
	 * @param $id Integer: Идентификатор аккаунта
	 * @param $email String: Новый email
	 */
	public function change_email($id, $email)
	{
		$this->update_user($id, array('email' => $email, 'new_email' => "", 'code' => ""));
	}

	/**
	 * Возвращает внутренний статус редиректа
	 */
	public function get_redirect()
	{
		return $this->redirect;
	}

	/**
	 * Выполняет подтверждение регистрации аккаунта и назначает стандартные права доступ
	 * @param $id Integer: Идентификатор аккаунта
	 * @param $code String: Код подтверждения
	 * @retval true Аккаунт подтверждён успешно
	 * @retval false Аккаунт с идентификатором $id не существует, либо код подтверждения неверен
	 */
	public function approve($id, $code)
	{
		$user = $this->get_simple_by("id", $id);

		if ($user === false)
		{
			$this->Errors->add(array("auth", "approve"), "Пользователь с данным ID не существует");
			return false;
		}

		if (empty($user['code']))
		{
			$this->Errors->add(array("auth", "approve"), "Ваша регистрация уже была подтверждена");
			return false;
		}

		if ($user['code'] != $code)
		{
			$this->Errors->add(array("auth", "approve"), "Неверный код подтверждения");
			return false;
		}

		$user['access'] = $user['access'] | self::ACCESS_APPROVED | self::ACCESS_DEFAULT;

		$this->update_user($id, array('access' => $user['access'], 'code' => ""));

		$this->Mail->send($user['email'], "Шаблоны/Письма/Регистрация/Подтверждение", $user);

		$this->login_force("id", $id);

		return true;
	}

	/**
	 * Отправляет на email адрес аккаунта письмо повторного подтверждения регистрации
	 * @param $id Integer Идентификатор аккаунта
	 * @retval true Письмо повторного подтверждения регистрации успешно отправлено
	 * @retval false Аккаунт с идентификатором $id не существует либо уже подтверждён
	 */
	public function reapprove($id)
	{
		$user = $this->get_simple_by("id", $id);

		if ($user === false)
		{
			$this->Errors->add(array("auth", "reapprove"), "Пользователь с данным ID не существует");
			return false;
		}

		if (empty($user['code']))
		{
			$this->Errors->add(array("auth", "reapprove"), "Ваша регистрация уже была подтверждена");
			return false;
		}

		$this->Mail->send($user['email'], "Шаблоны/Письма/Регистрация/Повторная отправка", $user);

		return true;
	}

	/**
	 * Отправляет на email адрес аккаунта письмо с информацией о восстановлении пароля
	 * @param $data Array: Информация для поиска аккаунта<br />
	 *	Требуемые элементы:<br />
	 *	login	- Логин<br />
	 *	<b>ИЛИ</b><br />
	 *	email	- Пароль
	 * @retval true Письмо с информацией о восстановлении пароля  успешно отправлено
	 * @retval true Аккаунт с логином $data['login'] или email адресом $data['email'] не существует
	 */
	public function restore_password($data)
	{
		if (empty($data['login']) && empty($data['email']))
		{
			$this->Errors->add(array("auth", "restore"), "Вы должны указать ваш логин или email");
			return false;
		}

		if (!empty($data['login']))
			$type = "login";
		else
			$type = "email";

		$row = $this->get_by($type, $data[$type]);
		
		if ($row == false)
		{
			$this->Errors->add(array("auth", "restore"), "Указаны несуществующие логин или email");
			return false;
		}

		$row['ip']		= $this->Common->get_ip_string();
		
		$row['password']	= $this->Common->gen_password(ACCOUNTS_PASSWORD_LENGTH);
		$password_encrypted = $this->encrypt($row['password']);
		
		$this->update_user($row['id'], array('password' => $password_encrypted));

		$this->Mail->send($row['email'], "Шаблоны/Письма/Восстановление пароля/Пароль", $row);

		return true;
	}

	/**
	 * Отправляет письмо с подтверждением прав владения email
	 * @param $id Integer: Идентификатор аккаунта
	 * @param $email String: Новый email адрес аккаунта
	 * @retval true Письмо с подтверждением прав владения email успешно отправлено
	 * @retval false Email адрес $email некорректен, либо он уже был использован при регистрации другого пользователя, либо пользователя с идентификатором $id не существует
	 */
	public function confirm_email($id, $email)
	{
		if (!$this->check_email($email))
			return false;

		$row = $this->get_simple_by("id", $id);
		if ($row == false)
		{
			$this->Errors->add(array("auth", "restore"), "Данный пользователь не существует");
			return false;
		}

		$row['ip']		= $this->Common->get_ip_string();
		$row['code']		= $this->Common->gen_confirm_code();
		$row['new_email']	= $email;

		$this->update_user($row['id'], array('new_email' => $row['new_email'], 'code' => $row['code']));

		$this->Mail->send($email, "Шаблоны/Письма/Подтверждение email", $row);

		return true;
	}

	/**
	 * Возвращает данные аккаунта по его идентификатору
	 * @param $id Integer: Идентификатор аккаунта
	 * @retval Array Данные аккаунта
	 */
	public function get($id = false)
	{
		$id = $this->used_id($id);
		if ($id === false)
			return false;

		return $this->get_by("id", $id);
	}

	/**
	 * Выполняет поиск аккаунта по паре ключ:значение и возвращает его дополнительные данные
	 * @param $type String: Ключ
	 * @param $value String: Значение
	 * @retval DatabaseResult Дополнительные данные аккаунта
	 */
	public function get_simple_by($type, $value)
	{
		$result = $this->DB->get_simple(array($type => $value));
		if ($result->is_empty())
			return false;

		return $result->fetch();
	}

	/**
	 * Обновляет дополнительные данные аккаунта
	 * @param $id Integer: Идентификатор аккаунта
	 * @param $data Array: Изменённые дополнительные данные аккаунта
	 */
	public function update_user_data($id, $data)
	{
		$this->DB->update_data($data, $id);
		$this->request_update($id);
	}

	/**
	 * Выполняет проверку на наличие прав доступа
	 * @param $access Integer: Требуемые права доступа
	 * @param $current_access Integer: Альтернативные права доступа, в которых будет производится проверка
	 * @retval true Права переданные в $current_access, либо пользователь обладают правом $access
	 * @retval false Права переданные в $current_access, либо пользователь НЕ обладают правом $access
	 */
	public function check_access($access, $current_access = false)
	{
		if ($current_access === false)
		{
			if (!$this->is_authed())
				return false;

			$current_access = $_SESSION['access'];
		}

		if (($current_access & $access) != $access)
			return false;

		return true;
	}

	/**
	 * Возвращает результат запроса всех аккаунтов
	 * @retval DatabaseResult Результат запроса
	 */
	public function get_all()
	{
		return $this->DB->get();
	}

	/**
	 * Отправляет данные клиентов в JSON формате
	 */
	public function send_data()
	{
		$table = array(
			'fields'	=> array( "id", "email", "reg_time", "last_login" , "phone" ,"name", "sex", "birth", "access"),
			'count'		=> array(&$this->DB, "get_data_count"),
			'data'		=> array(&$this->DB, "get_data")
		);

		$this->Tables->send($table);
	}

	protected function set_login_data($data)
	{
		$data['last_login'] = time();
		$this->DB->update(array('last_login' => $data['last_login']), $data['id']);

		parent::set_login_data($data);
	}

	protected function check_data($data, $password)
	{
		if (!parent::check_data($data, $password))
			return false;
		
		if (!parent::check_data($data, $password) && $data['new_password'] != $password)
		{
			$this->redirect = "/Восстановление+пароля/";
			return false;
		}

		if (($data['access'] & self::ACCESS_APPROVED) != self::ACCESS_APPROVED)
		{
			$this->Errors->add(array("auth", "approve"), "Вы ещё не подтвердили вашу регистрацию");
			$this->redirect = "/Повторная+отправка/?id={$data['id']}";
			return false;
		}

		$this->redirect = "";
		return true;
	}

	protected function check_email($email)
	{
		if (!parent::check_email($email))
			return false;

		$row = $this->get_simple_by("email", $email);
		if ($row !== false)
		{
			$this->Errors->add(array("auth", "email"), "Данный e-mail уже был использован при регистрации другого пользователя");
			return false;
		}

		return true;
	}

	protected function get_params()
	{
		return array(
			'prefix'	=> ACCOUNTS_SESSION_PREFIX,
			'salt'		=> ACCOUNTS_PASSWORD_SALT
		);
	}
}

?>