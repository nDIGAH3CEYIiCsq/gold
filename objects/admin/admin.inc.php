<?php

/**
 * Предоставляет функции регистрации и авторизации пользователей в панели администратора
 *
 * @uses DatabaseInterface
 * @uses ObjectErrors
 * @uses ObjectLog
 * @uses ObjectSessions
 * @uses ObjectTables
 *
 * @version 1.0.3
 */
class ObjectAdmin extends ObjectSessions implements DatabaseInterface
{
	static public function get_queries()
	{
		return array(
			'get'			=> "SELECT * FROM @pusers @W",
			'add'			=> "INSERT INTO @pusers SET @a",
			'update'		=> "UPDATE @pusers SET @a WHERE id = @i",
			'delete'		=> "DELETE FROM @pusers WHERE id = @i",

			'get_data'		=> "SELECT id, login, email, reg_time, last_login, access FROM @pusers @@WL @O @L",
			'get_data_count'	=> "SELECT count(*) as total FROM @pusers @@W",
		);
	}

	public function register($data)
	{
		$data['login'] = trim($data['login']);
		$data['email'] = trim($data['email']);

		if (!isset($data['access']) || $data['access'] == "")
			$data['access'] = array();

		if (!($this->check_login($data['login']) & $this->check_password($data['password']) & $this->check_email($data['email'])))
			return false;

		$row = $this->get_by("login", $data['login']);
		if ($row !== false)
		{
			$this->Errors->add(array("auth", "register"), "Пользователь с таким логином уже существует");
			return false;
		}

		$data['access'] = serialize($data['access']);
		$data['reg_time'] = time();
		$data['password'] = $this->encrypt($data['password']);

		$this->DB->add($data);

		return true;
	}

	public function unregister($id)
	{
		$this->DB->delete($id);

		if ($this->DB->affected_rows == 0)
			return false;

		$this->request_update($id);
		return true;
	}

	public function change_password($id, $old_password, $password)
	{
		if (!$this->check_password($password))
			return false;

		$password = $this->encrypt($password);

		$this->update_user($id, array('password' => $password));
		return true;
	}

	public function get_by($type, $value)
	{
		$result = $this->DB->get(array($type => $value));
		if ($result->is_empty())
			return false;

		return $result->fetch();
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
	 * Выполняет проверку на наличие прав доступа
	 * @param $access Integer: Требуемые права доступа
	 * @param $user_access Integer: Альтернативные права доступа, в которых будет производится проверка
	 * @retval true Права переданные в $current_access, либо пользователь обладают правом $access
	 * @retval false Права переданные в $current_access, либо пользователь НЕ обладают правом $access
	 */
	public function check_access($access, $user_access = false)
	{
		if ($user_access === false)
		{
			if (!$this->is_authed())
				return false;

			$user_access = $_SESSION['access'];
		}

		$access = explode("_", $access);
		while (list(, $piece) = each($access))
		{
			if (!isset($user_access[$piece]))
				return false;

			$user_access = &$user_access[$piece];
		}

		return true;
	}

	/**
	 * Изменяет права доступа аккаунта
	 * @param $id Integer: Идентификатор пользователя
	 * @param $access Array: Новый массив прав
	 */
	public function change_access($id, $access)
	{
		$access = serialize($access);

		$this->update_user($id, array('access' => $access));
	}

	/**
	 * Сохраняет пару ключ:значение во внутренних данных пользователя
	 * @param $key String: Ключ
	 * @param $value Mixed: Значение
	 */
	public function data_set($key, $value)
	{
		if (!$this->is_authed())
			return;

		if (!isset($_SESSION['data']))
			$_SESSION['data'] = array();

		$_SESSION['data'][$key] = $value;

		$this->data_save();
	}

	/**
	 * Возвращает значение по ключу из внутренних данных пользователя
	 * @param $key String: Ключ
	 * @retval Mixed Значение
	 */
	public function data_get($key)
	{
		if (!$this->is_authed())
			return false;

		if (!isset($_SESSION['data'][$key]))
			return false;

		return $_SESSION['data'][$key];
	}

	/**
	 * Возвращает результат запроса из БД всех аккаунтов
	 * @retval DatabaseResult Результат запроса
	 */
	public function get_all()
	{
		return $this->DB->get();
	}

	/**
	 * Отправляет данные пользователей в JSON формате
	 */
	public function send_data()
	{
		$table = array(
			'fields'	=> array("id", "login", "email", "reg_time", "last_login", "access"),
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

	protected function get_data()
	{
		$data = parent::get_data();

		$data['access']	= serialize($data['access']);
		$data['data']	= serialize($data['data']);

		return $data;
	}

	protected function set_data($data)
	{
		$data['access'] = unserialize($data['access']);
		if ($data['access'] === false)
			$this->Log->error("Error reading user access data");

		if (!empty($data['data']))
			$data['data'] = unserialize($data['data']);
		else
			$data['data'] = array();

		parent::set_data($data);
	}

	protected function get_params()
	{
		return array(
			'prefix'	=> ADMIN_SESSION_PREFIX,
			'salt'		=> ADMIN_PASSWORD_SALT
		);
	}

	private function data_save()
	{
		$id = $this->used_id();
		if ($id === false)
			return;

		if (!empty($_SESSION['data']))
			$data = serialize($_SESSION['data']);
		else
			$data = "";

		$this->update_user($id, array('data' => $data));
	}
}

?>