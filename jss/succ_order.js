$(document).ready(function()
{
	$("#go_main_btn").bind("click", function()
	{
		location = "/";
	});

	$("#go_orders_btn").bind("click", function()
	{
		location = "/Личный кабинет/Мои заказы/";
	});
});