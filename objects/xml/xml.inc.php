<?php

/**
 * Реализует оборачивание данных в CDATA секции при работе с XML
 *
 * @uses SimpleXMLElement
 *
 * @version 1.0.2
 */
class SimpleXMLExtended extends SimpleXMLElement
{
	/**
	 * Добавляет дочерний элемент в XML контейнер
	 * @param $name String: Имя элемента
	 * @param $value String: Значение элемента
	 * @param $namespace String: Пространство имён
	 * @retval SimpleXMLElement Добавленный элемент
	 */
	public function addChild($name, $value = null, $namespace = null)
	{
		$child = parent::addChild($name, $value, $namespace);
		return $child;
		if ($value === null)
			return $child;

		$node = dom_import_simplexml($child);
		$owner = $node->ownerDocument;
		$node->appendChild($owner->createCDATASection($value));

		return $child;
	}
}

/**
 * Предоставляет функции генерации XML данных
 *
 * @uses SimpleXMLExtended
 *
 * @version 1.0.2
 */
class ObjectXML extends Object
{
	/**
	 * Создаёт XML объект для отправки ответа клиенту
	 * @retval SimpleXMLExtended XML объект
	 */
	public function start_answer()
	{
		return new SimpleXMLExtended("<answer />");
	}

	public function create($value)
	{
		return new SimpleXMLExtended($value);
	}

	/**
	 * Отправляет текстовое сообщение об ошибке клиенту
	 * @param $error String: Сообщение об ошибке
	 */
	public function send_error($error)
	{
		$xml = $this->start_answer();

		$xml->addChild("errors", $error);

		$this->send_xml($xml);
	}

	/**
	 * Отправляет пустое сообщение клиенту
	 */
	public function send_empty()
	{
		$xml = $this->start_answer();

		$this->send_xml($xml);
	}

	/**
	 * Отправляет XML данные клиенту
	 * @param $xml SimpleXMLExtended: XML объект
	 */
	public function send_xml($xml)
	{
		Component::print_headers("text/xml");
		echo $xml->asXML();
		exit;
	}

	/**
	 * Перехватывает весь вывод и отправляет его в XML-контейнере
	 * @param $element String: Имя элемента, в котром будет находится вывод
	 */
	public function start_xml($element = "content")
	{
		ob_start();

		register_shutdown_function(array($this, "send_as_xml"), $element);
	}

	/**
	 * Отправляет данные инкапсулированными в XML-объект
	 * @param $element String: Данные для передачи
	 */
	public function send_as_xml($element)
	{
		$buffer = ob_get_clean();

		$xml = $this->start_answer();
		$xml->addChild($element, $buffer);

		Component::print_headers("text/xml");

		echo $xml->asXML();
		exit;
	}

	/**
	 * Записывает данные массива в виде аттрибутов XML объекта
	 * @param $xml_node SimpleXMLExtended: XML объект
	 * @param $data Array: Данные для записи
	 */
	public function write_attributes(&$xml_node, $data)
	{
		while (list($key, $value) = each($data))
			$xml_node->addAttribute($key, $value);
	}

	/**
	 * Записывает данные массива в виде дочерних элементов XML объекта
	 * @param $xml_node SimpleXMLExtended: XML объект
	 * @param $data Array: Данные для записи
	 * @param $node_name String: Имя дочернего элемента
	 */
	public function write_nodes(&$xml_node, $data, $node_name)
	{
		while (list(, $value) = each($data))
			$xml_node->addChild($node_name, $value);
	}
}

?>