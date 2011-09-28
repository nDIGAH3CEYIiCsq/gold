/**
 * Add Orders
 * @uses jQuery Form
 * @version 1.0.1
 */
Loader.scripts(["validator"]);
Loader.scripts(["jquery-form"], "add_order_init");

function add_order_init()
{
	$("#add_product_btn").bind("click", function()
	{
		Validator.reset();
		
		var count_element = $("#count");
		var count = count_element.val();
		var size = $("#size").val();
		size.toString().replace(".", ",");
		var article = $("#article").val();
		article = trim(article);
		count = parseInt(count);
		if (isNaN(count) || count <= 0)
		{
			$("#products_error").text("Не корректно введно кол-во изделий");
			return;
		}
		if (article == "")
		{
			$("#products_error").text("Не введен артикул изделия");
			return;
		}
		size = trim(size);
		if (size != "")
		{
			size = parseFloat(size);
			if (isNaN(size) || size <= 0  || size > 100)
			{
				$("#products_error").text("Не корректно введен размер");
				return;
			}
		}
		var exists_products = $("#products option");
		for (var i = 0; i < exists_products.length; i++ )
		{
			var option = exists_products.eq(i);
			var value = option.attr("value");
			var items = value.toString().split("|");
			if (article == items[0] && size == items[2])
			{
				$("#products_error").text("Указанный продукт уже введен");
				return;
			}

		}
		$("#products").append("<option value='" + article + "|"+ count + "|" + size + "'>артикуль:" + article + "&nbsp;&nbsp;&nbsp;кол-во:" + count + "&nbsp;&nbsp;&nbsp;размер:" + size + "</option>");

		var old_value = $("#products_list").val();
		var addin_value = "";
		if (old_value != "")
			addin_value += "&";
		addin_value += old_value;
		$("#products_list").val(article + "|"+ count + "|" + size + addin_value);
	});
}

$(document).ready(add_order_init);