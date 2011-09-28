<?php

/**
 * Форма обратной связи
 * @uses ObjectCaptcha
 * @uses ObjectEasyForms
 * @uses ObjectHandlers
 * @uses ObjectMail
 * @uses ObjectTemplates
 * @version 1.0.1.1
 */
class UserFeedback extends ComponentUser
{
	public function on_send_feedback($data)
	{
		if (!$this->EasyForms->field("send"))
			return;

		$params = $data['params'];

		$this->check_params($params, array("captcha", "email", "template", "success_page", "title", "fields"));
		
		$fields = $this->EasyForms->fields($params['fields']);
		if ($fields === false)
			return;

		if ($params['captcha']  == true)
			if (!$this->Captcha->check())
			{
				$this->Templates->bind_params($fields, "field_");
				return;
			}
		$fields['Title'] = $params['title'];

		$this->Questions->add(array(	'question'	=> $fields['message'],
						'name'		=> $fields['name'],
						'phone'		=> $fields['phone'],
						'email'		=> $fields['email'],
						'order_id'	=> $fields['order_id']));

		$this->Mail->send($params['email'], $params['template'], $fields);

		$this->Templates->set_page($params['success_page']);

	}

	public function on_call($data)
	{
		$params = $data['params'];

		$this->check_params($params, array("email", "template"));

		$fields = array('name'		=> array('require' => false),
				'phone'		=> array(),
				'question'	=> array('require' => false)
		    );
		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			$this->XML->send_error("error");

		$this->Mail->send($params['email'], $params['template'], $fields);
		exit;
		$this->XML->send_empty();
	}
}

?>