<?php

/**
 * Реализует генерацию и отображение Captcha
 *
 * @uses ComponentUser
 * @uses ObjectCaptcha
 */
class UserCaptcha extends ComponentUser
{
	/**
	 * Выводит капчу на экран
	 */
	public function on_captcha($data)
	{
		$this->Captcha->output();
	}
}

?>