/**
 * Basket
 * @uses Numbers
 * @uses Ajax
 * @uses Validator
 */

Loader.scripts(["numbers", "ajax","validator"]);

var count_products = {};

function basket_init()
{
	basket_init_btns();
	basket_load_data()
}

function basket_init_btns()
{
	$("#order_btn").bind("click", function()
	{
		location = "/Заказ/Оформление/";
		return false;
	});

	$(".basket_delete_link").bind("click", function()
	{
		Validator.reset();
		var element = $(this);
		var id = element.next("div").text();
		var tr = element.closest("tr");
		
		basket_remove_item(tr);

		var data = {'id': id, "size": basket_get_size(tr)};
		var xml = Ajax.post("/delete_basket/", data);
		basket_empty_page();
		basket_update(xml);

		count_products[id + "_" + data['size']] =  undefined;

		return false;
	});

	$(".product_count").bind("change", function()
	{
		var element = $(this);
		var tr = element.closest("tr");
		var new_count = element.val();
		if (!is_int_positive(new_count, 20))
		{
			var product_size = basket_get_size(tr);
			var product_id = tr.find(".id").text();
			element.val(count_products[product_id + "_" + product_size]);
		}
		else
		{
			element.val(new_count);
			basket_save_count(tr, new_count, true);
		}
	});

	$(".product_sizes").bind("change", function()
	{
		var element = $(this);
		var tr = element.closest("tr");
		var product_id = tr.find(".id").text();
		var new_size = basket_get_size(tr);
		if (new_size == "")
			return false;

		var old_size = tr.find(".product_size").text();
		Ajax.post("/set_size_basket/", {'id': product_id, 'new_size': new_size, 'old_size': old_size});
		tr.find("product_sizes option[value='']").remove();

		return false;
	});
}

function basket_load_data()
{
	var trs = $(".basket tbody tr:odd");
	for (var i = 0; i < trs.length; i++)
	{
		var tr = trs.eq(i);
		var count = tr.find(".product_count").val();
		basket_save_count(tr, count);
	}
}

function basket_save_count(tr, new_count, post)
{
	var product_id = tr.find(".id").text();
	var product_size = basket_get_size(tr);

	tr.find(".product_size").text(product_size);

	count_products[product_id + "_" + product_size] = new_count;

	if (typeof post != "undefined" && post == true)
	{
		var xml = Ajax.post("/set_count_basket/",  {'id': product_id, 'size': product_size, 'count': new_count});
		basket_update(xml);
		if (new_count == 0)
		{
			basket_remove_item(tr);
			basket_empty_page();
		}

	}
}

function basket_get_size(tr)
{
	var product_size = tr.find(".product_sizes");
	product_size.removeClass("error_selected");
	if (product_size.length)
		product_size = product_size.val();
	else
		product_size = "";

	product_size = product_size.replace(",", ".");

	return product_size;
}

function basket_empty_page()
{
	if ($(".basket tbody tr").length == 1)
		location = "/Корзина/";
}

function basket_remove_item(tr)
{
	tr.next("tr").remove();
	tr.remove();
}

$(document).ready(basket_init);