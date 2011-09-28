<?php

/**
 * Предоставляет функции работы с изображениями
 *
 * @version 1.0.2
 */
class ObjectImages extends Object
{
	/**
	 * Отправляет клиенту белое изображение размером 1x1 пикселов
	 */
	public function return_pixel()
	{
		header('Content-type: image/jpeg');

		$image		= imagecreatetruecolor(1, 1);
		$backcolor	= imagecolorallocate($image, 0xFF, 0xFF, 0xFF);

		imagesetpixel($image, 0, 0, $backcolor);
		imagejpeg($image);
		imagedestroy($image);
		exit;
	}

	/**
	 * Копирует изображение
	 * @param $source String: Источник
	 * @param $destination String: Назначение
	 * @retval true Изображение успешно скопировано
	 * @retval false Скопировать изображение не удалось
	 */
	public function image_copy($source, $destination)
	{
		$size = @getimagesize($source);
		if ($size === false)
			return false;

		if (!preg_match('/.+\/(.+)$/', $size['mime'], $matches))
			return false;

		$icfunc = "imagecreatefrom".$matches[1];
		if (!function_exists($icfunc))
			return false;

		$isrc = $icfunc($source);
		imagejpeg($isrc, $destination, IMAGES_QUALITY);

		return true;
	}

	/**
	 * Копирует изображение с масштабированием
	 * @param $source String: Источник
	 * @param $destination String: Назначение
	 * @param $width Integer: Требуемая длинна
	 * @param $height Integer: Требуемая высота
	 * @retval true Изображение успешно скопировано
	 * @retval false Скопировать изображение не удалось
	 */
	public function image_copy_resampled($source, $destination, $width, $height)
	{
		if (!file_exists($source))
			return false;

		$size = getimagesize($source);
		if ($size === false)
			return false;

		if (!preg_match('/.+\/(.+)$/', $size['mime'], $matches))
			return false;

		$icfunc = "imagecreatefrom".$matches[1];
		if (!function_exists($icfunc))
			return false;

		if ($size[0] <= $width && $size[1] <= $height)
		{
			$isrc = $icfunc($source);
			imagejpeg($isrc, $destination, IMAGES_QUALITY);
			return true;
		}

		$x_ratio	= $width / $size[0];
		$y_ratio	= $height / $size[1];
		$ratio		= min($x_ratio, $y_ratio);
		$use_x_ratio	= ($x_ratio == $ratio);
		$new_width	= $use_x_ratio ? $width : floor($size[0] * $ratio);
		$new_height	= !$use_x_ratio ? $height : floor($size[1] * $ratio);

		$isrc		= $icfunc($source);
		$idest		= imagecreatetruecolor($new_width, $new_height);
		$backcolor	= imagecolorallocate($idest, 0xFF, 0xFF, 0xFF);

		imagefill($idest, 0, 0, $backcolor);
		imagecopyresampled($idest, $isrc, 0, 0, 0, 0, $new_width, $new_height, $size[0], $size[1]);

		imagejpeg($idest, $destination, IMAGES_QUALITY);
		imagedestroy($isrc);
		imagedestroy($idest);
		return true;
	}

	/**
	 * Возвращает минимальные размеры прмоугольника, в который может быть вписан текст
	 * @param $font String: Путь к файлу шрифта
	 * @param $text String: Текст
	 * @param $size String: Размер текста
	 * @retval Array Размеры прямоугольника
	 */
	public function get_sizes($font, $text, $size)
	{
		$bbox = imagettfbbox($size, 0, $font, $text);

		$ret = array();
		$ret['width'] = $bbox[2] - $bbox[0];
		$ret['height'] = $bbox[1] - $bbox[7];

		return $ret;
	}
}

?>