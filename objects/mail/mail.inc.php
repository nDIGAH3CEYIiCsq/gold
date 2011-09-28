<?php

/**
 * Предоставляет функции отправки почты
 *
 * @uses ObjectLog
 * @uses ObjectTemplates
 *
 * @version 1.0.2
 */
class ObjectMail extends Object
{
	/**
	 * Отправляет письмо
	 * @param $emails Array: Список email адресов получателей
	 * @param $template_name String: Имя шаблона письма
	 * @param $data Array: Данные письма
	 * @retval true Отправка письма удалась всем получателям
	 * @retval false Отправка письма не удалась, либо передан некорректный email
	 */
	public function send($emails, $template_name, $data)
	{
		if (empty($emails))
			return true;
		if (!is_array($emails))
			$emails = array($emails);

		reset($emails);
		while (list(, $email) = each($emails))
		{
			if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
				return false;
		}

		$template = $this->Templates->get($template_name);
		
		$template->bind_params($data);

		$params = $template->get_params(array("Title", "From"));
		if ($params === false)
			$this->Log->error("Can't find mail params");

		$params['Reply-To'] = $template->get_param("Reply-To");
		if ($params['Reply-To'] === false)
			$params['Reply-To'] = $params['From'];

		$params['Content-Type'] = $template->get_param("Content-Type");
		if ($params['Content-Type'] === false)
			$params['Content-Type'] = "text/plain; charset=".MAIL_CHARSET;

		$subject = (string) $params['Title'];
		$message = (string) $template;
		$headers = "From: {$params['From']}\r\nReply-To: {$params['Reply-To']}\r\nContent-Type: {$params['Content-Type']}";

		reset($emails);
		while (list(, $email) = each($emails))
			mail($email, $subject, $message, $headers);

		return true;
	}
}

?>