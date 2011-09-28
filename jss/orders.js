$(document).ready(function()
{		
	$("#confirm_cancel_btn").bind("click", function()
	{
		location = "/Заказ/Незарегистрованный пользователь/";
	});

	var delivery = $("#bind_delivery").text();
	$("input[name='delivery'][value='" + delivery + "']").attr('checked', true);

	var pay = $("#bind_pay").text();
	$("input[name='pay'][value='" + pay + "']").attr('checked', true);

	$("input[name='delivery']").bind("click", function()
	{
		$(".delivery").hide();
		$(this).closest("td").find(".description").show();
	});

	$("input[name='pay']").bind("click", function()
	{
		$(".pay").hide();
		$(this).closest("td").find(".description").show();
	});

	Validator.map({'order': "delivery"});
})

