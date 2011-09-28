<?php

/**
 * Предоставляет функции работы с Captcha
 *
 * @uses ObjectAccounts
 * @uses ObjectCommon
 * @uses ObjectEasyForms
 * @uses ObjectErrors
 *
 * @version 1.0.3
 */
class ObjectCaptcha extends Object
{
	const SymbolWidth	= 25;
	const ImageHeight	= 45;
	const FontSize		= 25;
	const PointsCount	= 500;

	public function __construct(&$objects)
	{
		parent::__construct($objects);

		$this->Accounts->init();
	}

	/**
	 * Выполняет проверку правильности ввода капчи
	 * @retval true Текст капчи введён правильно
	 * @retval false  Текст капчи введён неправильно
	 */
	public function check()
	{
		$fields = array(
			'captcha' => array('caption' => "Защита от спама"),
		);

		$fields = $this->EasyForms->fields($fields);
		if ($fields === false)
			return false;

		$fields['captcha'] = strtoupper($fields['captcha']);

		if (!isset($_SESSION['captcha']) || $_SESSION['captcha'] != $fields['captcha'])
		{
			$this->Errors->add("simple", array('captcha' => "Код проверки введён неправильно"));
			return false;
		}

		unset($_SESSION['captcha']);
		return true;
	}

	/**
	 * Отправляет данные картинку капчи на сервер
	 */
	public function output()
	{
		$captcha = $this->Common->gen_password(CAPTCHA_LENGTH, false);
		$captcha = strtoupper($captcha);

		$_SESSION['captcha'] = $captcha;

		$width		= 15 + CAPTCHA_LENGTH * self::SymbolWidth + 15;

		$image		= imagecreatetruecolor($width, self::ImageHeight);
		$backcolor	= imagecolorallocate($image, 0xFF, 0xFF, 0xFF);

		imagefill($image, 0, 0, $backcolor);

		for ($i = 0; $i < self::PointsCount; $i++)
		{
			$color = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
			imagesetpixel($image, mt_rand(0, $width), mt_rand(0, self::ImageHeight), $color);
		}

		for ($i = 0; $i < CAPTCHA_LENGTH; $i++)
		{
			$color = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
			imagettftext($image, self::FontSize, mt_rand(-15, 15), 15 + $i * self::SymbolWidth, 35, $color, CAPTCHA_FONT_LOCATION, $captcha[$i]);
		}

		Component::print_headers("image/png", false);

		imagepng($image);
		imagedestroy($image);

		exit;
	}
}

?>