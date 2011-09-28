<?php

/**
 * Предоставляет функции работы с сессиями
 *
 * @uses ObjectCache
 * @uses ObjectErrors
 *
 * @version 1.0.3
 */
abstract class ObjectSessions extends Object
{
	const CacheClass = "sessions";

	private $session_params = null;

	public function __construct(&$objects)
	{
		parent::__construct($objects);

		$this->init();
	}

	/**
	 * Инициализирует сессию
	 */
	public function init()
	{
		if ($this->session_started())
			return;

		if ($this->session_params !== null)
			return;

		$this->session_params = $this->get_params();

		$this->load();
		$this->check_update();
	}

	/**
	 * Авторизирует пользователя по переданому логину и паролю
	 * @param $login String: Логин
	 * @param $password String: Пароль
	 * @retval true Пользователь успешно авторизован
	 * @retval false Пользователя авторизовать не удалось
	 */
	public function login($login, $password)
	{
		if (!($this->check_login($login) & $this->check_password($password)))
			return false;

		$password = $this->encrypt($password);
		//echo $password;
		$row = $this->get_by("login", $login);
		//echo " ". $row['password'];
		if (!$this->check_data($row, $password))
			return false;

		$this->set_login_data($row);
		return true;
	}

	/**
	 * Авторизует пользователя по паре ключ:значение без проверки на правильность пароля
	 * @param $type String: Ключ поиска
	 * @param $value String: Значение поиска
	 * @retval true Пользователь успешно авторизован
	 * @retval false Пользователь с такой парой ключ:значение не существует
	 */
	public function login_force($type, $value)
	{
		$row = $this->get_by($type, $value);
		if ($row === false)
			return false;

		$this->set_login_data($row);
		return true;
	}

	/**
	 * Выполняет выход пользователя из системы
	 */
	public function logout()
	{
		$this->clear_data();
		$this->close();
	}

	/**
	 * Возвращает флаг авторизации пользователя в системе
	 * @retval true Пользователь авторизован
	 * @retval false Пользователь не авторизован
	 */
	public function is_authed()
	{
		if (!isset($_SESSION['id']))
			return false;
		return true;
	}

	/**
	 * Возвращает либо используемый идентификатор аккаунта, либо идентификатор текущего пользователя
	 * @param $id Integer: Используемый идентификатор аккаунта
	 * @retval Integer Идентификатор аккаунта
	 */
	public function used_id($id = false)
	{
		if (!empty($id) && $id > 0)
			return $id;

		if (!$this->is_authed())
			return false;

		return $_SESSION['id'];
	}

	/**
	 * Проверяет, является ли авторизованный пользователь владельцем аккаунта
	 * @param $id Integer: Идентификатор аккаунта для проверки
	 * @retval true Пользователь является владельцем идентификатора
	 * @retval false Пользователь не является владельцем идентификатора
	 */
	public function check_owner($id)
	{
		if (!$this->is_authed())
			return false;

		return ($id == $_SESSION['id']);
	}

	/**
	 * Возвращает данные, содержащиеся в сессии анонимного пользователя
	 * @retval Array Данные анонимного пользователя
	 */
	public function get_anonymous_data()
	{
		if ($this->is_authed())
		{
			if (!isset($_SESSION['anonymous']))
				return array();
			return $_SESSION['anonymous'];
		}
		return $_SESSION;
	}

	/**
	 * Устанавливает данные пользователя при авторизации
	 * @param $data Array: Данные пользователя
	 */
	protected function set_login_data($data)
	{
		$anonymous = $_SESSION;

		$this->set_data($data);

		$_SESSION['anonymous'] = $anonymous;
	}

	/**
	 * Возвращает данные авторизированного пользователя
	 * @retval Array Данные пользователя
	 */
	protected function get_data()
	{
		return $_SESSION;
	}

	/**
	 * Сохраняет в сессии данные пользователя
	 * @param $data Array: Данные пользователя
	 */
	protected function set_data($data)
	{
		$_SESSION = $data;

		$update_id = $this->Cache->get($_SESSION['id'], self::CacheClass, false);
		if ($update_id === false)
			$update_id = 0;

		$_SESSION['update_id'] = $update_id;
	}

	/**
	 * Очищает данные пользователя
	 */
	protected function clear_data()
	{
		$_SESSION = array();
	}

	/**
	 * Проверяет пароль на корректность
	 * @param $password String: Пароль
	 * @retval true Пароль корректен
	 * @retval false Пароль некорректен
	 */
	protected function check_password($password)
	{
		if (empty($password))
			$this->Errors->add(array("auth", "password"), "Не указан пароль пользователя");
		else if (mb_strlen($password) > SESSION_MAX_PASSWORD_LEN)
			$this->Errors->add(array("auth", "password"), "Пароль слишком длинный");
		else
			return true;

		return false;
	}

	/**
	 * Проверяет логин на корректность
	 * @param $login String: Логин
	 * @retval true Логин корректен
	 * @retval false Логин некорректен
	 */
	protected function check_login($login)
	{
		if (empty($login))
			$this->Errors->add(array("auth", "login"), "Не указан логин пользователя");
		else if (mb_strlen($login) > SESSION_MAX_LOGIN_LEN)
			$this->Errors->add(array("auth", "login"), "Логин слишком длинный");
		else if (preg_match("/[^a-zа-яё0-9_\-. ]/ui", $login) !== 0)
			$this->Errors->add(array("auth", "login"), "Логин содержит недопустимые символы");
		else
			return true;

		return false;
	}

	/**
	 * Проверяет email на корректность
	 * @param $email String: Email
	 * @retval true Email корректен
	 * @retval false Email некорректен
	 */
	protected function check_email($email)
	{
		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
			$this->Errors->add(array("auth", "email"), "Email некорректен");
		else
			return true;

		return false;
	}

	/**
	 * Проверяет данные пользователя на возможность авторизации
	 * @param $data String: Информация, переданная пользователем в момент авторизации
	 * @param $password String: Оригинальный пароль пользователя
	 * @retval true Парольвателя можно авторизовать
	 * @retval false Парольвателя нельзя авторизовать
	 */
	protected function check_data($data, $password)
	{
		if ($data === false || $data['password'] != $password)
			$this->Errors->add(array("auth", "data"), "Неправильный логин или пароль");
		else
			return true;

		return false;
	}

	/**
	 * Кодирует пароль пользователя
	 * @param $password String: Пароль в чистом виде
	 * @retval String Кодированный пароль
	 */
	protected function encrypt($password)
	{
		return strtoupper(sha1($this->session_params['salt'].sha1($this->session_params['salt'].$password)));
	}

	/**
	 * Запрашивает обновление данных пользователя при его следующем посещении портала
	 * @param $id Integer: Идентификатор пользователя
	 */
	protected function request_update($id)
	{
		$update_id = $this->Cache->increment($id, self::CacheClass);
		if ($update_id === false)
			$this->Cache->set($id, self::CacheClass, 1);
	}

	private function update()
	{
		$row = $this->get_by("id", $_SESSION['id']);
		if ($row === false)
		{
			$this->logout();
			return;
		}

		$this->set_data($row);
	}

	private function check_update()
	{
		if (!$this->is_authed())
			return;

		$update_id = $this->Cache->get($_SESSION['id'], self::CacheClass, false);
		if ($update_id === false || $_SESSION['update_id'] === $update_id)
			return;

		//$this->update();
	}

	private function session_started()
	{
		return (session_id() != "");
	}

	private function load()
	{
		session_cache_limiter("nocache");
		session_set_cookie_params(SESSION_LIFE_TIME, "/", ".".SESSION_DOMAIN_NAME);
		session_name($this->session_params['prefix']."_".SESSION_NAME);
		session_start();
	}

	private function close()
	{
		session_cache_limiter("nocache");
		session_set_cookie_params(0, "/", ".".SESSION_DOMAIN_NAME);
		session_name($this->session_params['prefix']."_".SESSION_NAME);
		session_destroy();
	}

	/**
	 * Регистрирует новый аккаунт
	 * @param $data Array: Данные нового аккаунта<br />
	 *	Требуемые элементы:<br />
	 *	login		- Логин<br />
	 *	password	- Пароль<br />
	 *	email		- Email
	 * @retval Integer Идентификатор аккаунта
	 * @retval false Если регистрация не удалась
	 */
	abstract public function register($data);

	/**
	 * Удаляет существующий аккаунт
	 * @param $id Integer: Идентификатор аккаунта
	 * @retval true Аккаунт успешно удалён
	 * @retval false Удаление аккаунта не удалось
	 */
	abstract public function unregister($id);

	/**
	 * Изменяет пароль аккаунта
	 * @param $id Integer: Идентификатор аккаунта
	 * @param $password String: Новый пароль аккаунта
	 * @retval true Пароль успешно изменён
	 * @retval false Изменение пароля не удалось
	 */
	abstract public function change_password($id, $old_password, $new_password);

	/**
	 * Выполняет поиск аккаунта по паре ключ:значение и возвращает его данные
	 * @param $type String: Ключ поиска
	 * @param $value String: Значение поиска
	 * @retval Array Данные аккаунта
	 * @retval false Если такой аккаунт не найден
	 */
	abstract public function get_by($type, $value);

	/**
	 * Возвращает параметры инициализации модуля
	 * @retval Array Параметры инициализации
	 */
	abstract protected function get_params();
}

?>