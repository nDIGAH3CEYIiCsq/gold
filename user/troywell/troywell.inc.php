<?php

/**
 * Реализует отображение и блокировку страниц сайта
 *
 * @uses ComponentUser
 * @uses ObjectTemplates
 *
 * @version 1.0.3
 */
class UserTroywell extends ComponentUser
{
	private $troywellStatusMap = array(
		'conditionally'	=> "wiat",
		'accept'	=> "new",
		'process'	=> "wait",
		'post'		=> "wait",
		'end'		=> "done",
		'cancel'	=> "cancel",
	);

	public function on_send_data()
	{
		$answer = $this->XML->create(	"<?xml version='1.0' encoding='windows-1251'?>".
						"<items />");

		$xml = $this->EasyForms->field("xml");
		if (empty($xml))
		{
			$error = $this->XML->create("<?xml version='1.0' encoding='windows-1251'?>".
						"<error>data empty</error>");
			return $this->XML->send_xml($error);
		}

		$query = $this->XML->create($xml);
		$children = $query->children();

		 foreach ($children as $k=>$v)
		 {
			$order = $this->get_order_by_id($v);
			if ($order === false)
				continue;
			$item =  $answer->addChild("item");
			$item->addChild("id", $order['id']);
			$item->addChild("status",$this->get_status($order['status']));
			$item->addChild("price", $order['price']);
		 }
		 return $this->XML->send_xml($answer);
	}

	function get_order_by_id($orderId)
	{
		return $this->Orders->get($orderId);
	}

	function get_status($status)
	{
		return $this->troywellStatusMap[$status];
	}
}
?>