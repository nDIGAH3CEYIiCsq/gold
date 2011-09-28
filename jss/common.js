/**
 * Common
 * @uses Ajax
 * @version 1.0.2
 */
Loader.scripts(["ajax", "option_links"]);


$(document).ready(function()
{
	basket_calc_words($(".basket_count").eq(0).text());

	$("#search_link").click(function()
	{
		submit_search_form();
		return false;
	});

	var category = $("#bind_search_category").val();
	if (category != "")
		$("#search_category").find("option[value='" + category  + "']").attr("selected", "selected");

	var metal = $("#bind_search_metal").val();
	if (metal != "")
		$("#search_metal").find("option[value='" + metal  + "']").attr("selected", "selected");

	if ($("#bind_search_complect").val() == "1")
		$("#search_complect").attr("checked","checked");

	if ($("#bind_search_men").val() == "1")
		$("#search_men").attr("checked","checked");

	var stone = $("#bind_search_stone").val();
	if (stone != "")
		$("#search_stone").find("option[value='" + stone  + "']").attr("selected", "selected");

	$("#search_price_min").val($("#bind_search_price_min").val());
	$("#search_price_max").val($("#bind_search_price_max").val());

	var price_options = new OptionLinks(
	{
		'price':
		{
			'switches': ["price1", "price2", "price3", "price4", "price5"],
			'handler': function(state)
			{
				switch(state)
				{
					case "price1":
					{
						set_prices(0, 2000);
						submit_search_form();
						break;
					}
					case "price2":
					{
						set_prices(2000, 5000);
						submit_search_form();
						break;
					}
					case "price3":
					{
						set_prices(5000, 10000);
						submit_search_form();
						break;
					}
					case "price4":
					{
						set_prices(10000, 20000);
						submit_search_form();
						break;
					}
					case "price5":
					{
						set_prices(20000, "");
						submit_search_form();
						break;
					}
				}

				return true;
			}
		}
	});

	if ($("#bind_search_price_min").val() == "0" && $("#bind_search_price_max").val() == "2000")
		price_options.set("price", "price1");
	if ($("#bind_search_price_min").val() == "2000" && $("#bind_search_price_max").val() == "5000")
		price_options.set("price", "price2");
	if ($("#bind_search_price_min").val() == "5000" && $("#bind_search_price_max").val() == "10000")
		price_options.set("price", "price3");
	if ($("#bind_search_price_min").val() == "10000" && $("#bind_search_price_max").val() == "20000")
		price_options.set("price", "price4");
	if ($("#bind_search_price_min").val() == "20000" && $("#bind_search_price_max").val() == "")
		price_options.set("price", "price5");

	$("#search_price_min, #search_price_max, #search_code").bind("focusout", function()
	{
		set_non_active_field($(this));
	});

	$("#search_price_min, #search_price_max, #search_code").bind("focus", function()
	{
		set_active_field($(this));
	});

	$("#search_price_min, #search_price_max, #search_code").bind("change", function()
	{
		set_active_field($(this));
	})

	check_active($("#search_price_min"));
	check_active($("#search_price_max"));
	check_active($("#search_code"));

	$("#call-dialog").dialog(
	{
		title: "Заказ обратного звонка",
		autoOpen: false,
		resizable: false,
		width: 471,
		height: 429,
		modal: true,
		buttons:{
				"Перезвонить мне!": function()
				{
					var name = $("#call-name").val();
					name = trim(name);
					var phone = $("#call-phone").val();
					phone = trim(phone);
					if (phone == "")
					{
						alert("Вы не указали телефон");
						return;
					}

					var data = Ajax.post("/call/", {'name': name,
									'question': $("#call-question").val(),
									'phone': phone});
					if (data.text() == "error")
					{
						alert("Вы не указали телефон")
						return;
					}
					$(this).dialog("close");
				}
			}
	});

	$("#call-link").bind("click", function()
	{
		$("#call-dialog").dialog("open");
		return false;
	});

	VK.Widgets.Group("vk_groups", {mode: 1, width: "200", height: "290"}, 19983201);
});

function submit_search_form()
{
	if ($("#search_code").val() == "Код")
		$("#search_code").val("");
	if ($("#search_price_min").val() == "Цена от")
		$("#search_price_min").val("");
	if ($("#search_price_max").val() == "Цена до")
		$("#search_price_max").val("");

	$("#search-form").submit();
}

function check_active(field)
{
	var id = field.attr("id");
	var value = trim(field.val());
	switch(id)
	{
		case "search_price_min":
		{
			if (value != "" && value != "Цена от" )
				set_active_class(field);
			else
			{
				field.val("Цена от");
				set_not_active_class(field);
			}
			break;
		}
		case "search_price_max":
		{
			if (value != "" && value != "Цена до")
				set_active_class(field);
			else
			{
				field.val("Цена до");
				set_not_active_class(field);
			}
			break;
		}
		case "search_code":
		{
			if (value != "" && value != "Код")
				set_active_class(field);
			else
			{
				field.val("Код");
				set_not_active_class(field);
			}
			break;
		}
	}
}

function basket_add(product_id, sizes, not_update_data)
{
	var data = {'id': product_id};
	if (typeof sizes != "undefined")
		data['sizes'] = sizes;

	var xml = Ajax.post("/add_basket/", data);
	if (typeof not_update_data != "undefined" && not_update_data == true)
		return;
	
	basket_update(xml);
}

function basket_update(xml)
{
	var basket = xml.find("basket");
	if (!basket.length)
		return;

	var price = basket.attr("price");
	var count = basket.attr("count");
	
	$(".basket_count").text(count);
	$(".basket_price").text(price);
	$("#price").val(price);

	basket_calc_words(count);
}

function basket_calc_words(count)
{
	var unit = $(".basket_unit");

	if (count >= 10 && count <= 20)
	{
		unit.text("изделий");
		return;
	}
	var number_last = count[count.length -1];

	if (number_last == 0)
		unit.text("изделий")
	if (number_last == 1)
		unit.text("изделие");
	else if (number_last > 1 && number_last <=4)
		unit.text("изделия");
	else if (number_last > 4 || number_last == 0)
		unit.text("изделий");
}

function set_prices(min, max)
{
	$("#search_price_min").val(min);
	$("#search_price_max").val(max);
	check_active($("#search_price_min"));
	check_active($("#search_price_max"));
}


function set_non_active_field(field)
{
	var id = field.attr("id");
	switch(id)
	{
		case "search_price_min":
		{
			if (field.val() != "" && field.val() != "Цена от")
				break;

			field.val("Цена от");
			set_not_active_class(field);
			return;
		}
		case "search_price_max":
		{
			if (field.val() != "" && field.val() != "Цена до")
				break;

			field.val("Цена до");
			set_not_active_class(field);
			return;
		}
		case "search_code":
		{
			if (field.val() != "" && field.val() != "Код")
				break;

			field.val("Код");
			set_not_active_class(field);
			return;
		}
	}


	set_active_class(field);
}

function set_active_field(field)
{
	var id = field.attr("id");

	switch(id)
	{
		case "search_price_min":
		{
			if (field.val() != "Цена от")
				break;

			field.val("");
			break;
		}
		case "search_price_max":
		{
			if (field.val() != "Цена до")
				break;

			field.val("");
			break;
		}
		case "search_code":
		{
			if (field.val() != "Код")
				break;

			field.val("");
			break;
		}
	}

	set_active_class(field);
}

function set_active_class(field)
{
	field.addClass("input_filled");
	field.removeClass("input_empty");
}

function set_not_active_class(field)
{
	field.addClass("input_empty");
	field.removeClass("input_filled");
}
