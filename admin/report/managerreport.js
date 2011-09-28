/**
 * ManagerReport
 * @uses Ajax
 * @uses SortedTable
 * @version 1.0.0
 */
Loader.styles(["jquery-datatables"]);
Loader.scripts(["ajax", "option_links", "jquery-form", "sorted_table", "validator"], "managerreport_init");

var orders_table

function managerreport_init()
{
	var options = new OptionLinks(
	{
		'period':
		{
			'switches': ["today", "yesterday", "current_week", "current_month", "current_year", "last_year", "set"],
			'default': "today",
			'handler': function(state)
			{
				if (state == "set")
				{
					$("#period_div_set").show();
					return false;
				}

				$("#period").text(state);
				orders_table.source(content_url({'action': "get_orders", 'period': $("#period").text(), 'status': $("#status").text()}));
				orders_table.redraw();

				return true;
			}
		},
		'status':
		{
			'switches': ["all", "accept", "process", "courier", "end", "return", "cancel"],
			'default': "all",
			'handler': function(state)
			{
				$("#status").text(state);
				orders_table.source(content_url({'action': "get_orders", 'period': $("#period").text(), 'status': $("#status").text()}));
				orders_table.redraw();

				return true;
			}
		}
	});

	$("#apply_period_link").bind("click", function()
	{
		$("#period_set").val(1);
		options.set("period", "set");
		orders_table.source(content_url({'action': "get_orders", 'month': $("#month").val(), 'year': $("#year").val()}));
		orders_table.redraw();
	})

	var form_options =
	{
		success: function(response)
		{
			if ($(response).find("errors").text() != "")
			{
				$("#email,#phone").addClass("errors_input");
				return;
			}

			orders_table.redraw();
		},
		dataType: "xml"
	}

	$('#add_buyer_form').submit(function()
	{
		$("#email,#phone").removeClass("errors_input");

		$(this).ajaxSubmit(form_options);

		return false;
	});

	orders_table = new SortedTable("orders",
	{
		'columns':
		[
			{
				"fnRender": function (oObj)
				{
					return "<a href='#' class='detail_column detail_open'><div>" + oObj.aData[0] + "</div></a>";
				},
				"aTargets": [0],
				sClass: "center",
				"bSortable": false
			},
			{sClass: "center"},
			{sClass: "left"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{
				"fnRender": function (oObj)
				{
					var id = oObj.aData[9];
					return "<a href='#' class='edit_column edit_order' title='Изменить'><div>" + id + "</div></a>" +
						"<a href='#' class='delete_column delete_order' title='Удалить'><div>" + id + "</div></a>" +
						"<a href='#' class='save_column save_order' style='display: none' title='Сохранить'><div>" + id + "</div></a>" +
						"<a href='#' class='cancel_column cancel_order' style='display: none' title='Отмена'><div>" + id + "</div></a>";
				},
				"aTargets": [9],
				sClass: "center",
				"bSortable": false
			}
		],
		'options':
		{
			sAjaxSource: content_url({'action': "get_orders", 'period': $("#period").text(), 'status': $("#status").text()})
		},
		"aaSorting": [[ 0, "desc" ]]
	});

	$(".delete_order").live("click", function()
	{
		var id = $(this).find("div").text();

		if (!confirm("Вы действительно хотите заказ № " + id + " ?"))
			return;

		content_get("delete", {'id': id});

		orders_table.redraw();
	});

	$(".edit_order").live("click", function()
	{
		var tr = $(this).closest("tr");

		tr.addClass("edit_row");

		var id = tr.find('td:eq(1)').text();

		var td_buyer = tr.find("td:eq(2)");
		var children = td_buyer.children();
		var name = children.eq(0).text();
		var phone = children.eq(1).text();
		var email = children.eq(2).text();
		var address = children.eq(3).text();

		td_buyer.html(	"<div class='buyer'>" +
				"<label for='name_" + id + "'>Имя:</label><input name='name' id='name_" + id + "' value='" + name + "' /><br />" +
				"<label for='name_" + id + "'>Телефон:</label><input name='phone' id='phone_" + id + "' value='" + phone + "' /><br />" +
				"<label for='name_" + id + "'>Email:</label><input name='email' id='email_" + id + "' value='" + email + "' /><br />" +
				"<label for='name_" + id + "'>Адрес:</label><input name='address' id='address_" + id + "' value='" + address + "' /><br />" +
				"<div class='old'>" + td_buyer.html() + "</div>" +
				"</div>");

		var td_payment_method = tr.find("td:eq(5)");
		var payment_method = trim(td_payment_method.text());
		td_payment_method.html("<select name='payment_method'>" +
						"<option value=''" + (payment_method == "" ? " selected": "") + "></option>" +
						"<option value='cash'" + (payment_method == "наличные" ? " selected": "") + ">наличные</option>" +
						"<option value='bank'" + (payment_method == "банковский перевод" ? "selected": "") + ">банковский перевод</option>" +
						"<option value='webmoney'" + (payment_method == "webmoney" ? "selected": "") + ">webmoney</option>" +
						"<option value='yandex'" + (payment_method == "yandex.деньги" ? "selected": "") + ">yandex.деньги</option>" +
					"</select><div class='old'>" + payment_method + "</div>");
		var td_delivery_method = tr.find("td:eq(6)");
		var delivery_method = trim(td_delivery_method.text());
		td_delivery_method.html("<select name='delivery_method'>" +
						"<option value=''" + (delivery_method == "" ? " selected": "") + "></option>" +
						"<option value='courier'" + (delivery_method == "курьер" ? " selected": "") + ">курьер</option>" +
						"<option value='post'" + (delivery_method == "почта" ? " selected": "") + ">почта</option>" +
					"</select><div class='old'>" + delivery_method + "</div>");

		var td_delivery_price = tr.find("td:eq(7)");
		td_delivery_price.html("<input type='text' name='delivery_price' value='" + td_delivery_price.text() + "' class='edit_order_delivery_price' /><div class='old'>" + td_delivery_price.text() + "</div>");

		var td_status = tr.find("td:eq(8)");
		var status = trim(td_status.text());
		td_status.html("<select name='status'>" +
						"<option value=''" + (status == "" ? " selected": "") + "></option>" +
						"<option value='accept'" + (status == "принят" ? " selected": "") + ">принят</option>" +
						"<option value='process'" + (status == "в обработке" ? " selected": "") + ">в обработке</option>" +
						"<option value='courier'" + (status == "готов к исполнению курьером" ? " selected": "") + ">готов к исполнению курьером</option>" +
						"<option value='end'" + (status == "выполнен" ? " selected": "") + ">выполнен</option>" +
						"<option value='cancel'" + (status == "отменен" ? " selected": "") + ">отменен</option>" +
						"<option value='return'" + (status == "возврат" ? " selected": "") + ">возврат</option>" +
					"</select><div class='old'>" + status + "</div>");

		tr.find(".edit_column, .delete_column").hide();
		tr.find(".save_column, .cancel_column").show();

	});

	$(".cancel_order").live("click", function()
	{
		var tr = $(this).closest("tr");

		cancel_edit_fill_td(tr, 2);
		for (var i = 5; i <= 8; i++)
			cancel_edit_fill_td(tr, i);

		tr.find(".edit_column, .delete_column").show();
		tr.find(".save_column, .cancel_column").hide();
	})

	$(".save_order").live("click", function()
	{
		var order_id = $(this).find("div").text();

		var tr = $(this).closest("tr");

		var buyer_td = tr.find("td:eq(2)");
		var name = buyer_td.find("input[name='name']").val();
		var phone = buyer_td.find("input[name='phone']").val();
		var email = buyer_td.find("input[name='email']").val();
		var address = buyer_td.find("input[name='address']").val();

		var payment_method = tr.find("td:eq(5) SELECT").val();
		var delivery_method = tr.find("td:eq(6) SELECT").val();
		var delivery_price = tr.find("td:eq(7) INPUT").val();
		var status = tr.find("td:eq(8) SELECT").val();

		var xml = content_get("edit_order", {'id': order_id, 'payment_method': payment_method, 'delivery_method': delivery_method, 'delivery_price': delivery_price, 'status': status, 'name': name, 'phone': phone, 'email': email, 'address': address});
		if (xml.find("errors"))
		{
			var errors = xml.find("errors").text();
			if (errors != "")
			{
				alert(errors);
				return;
			}
		}
		orders_table.redraw();
	})

	$('#orders_table .detail_column').live( 'click', function () 
	{
		var td = $(this);

		var nTr = this.parentNode.parentNode;
		if (!td.hasClass("detail_close"))
		{
			td.removeClass("detail_open");
			td.addClass("detail_close");
			orders_table.open(nTr, fnFormatDetails);
		}
		else
		{
			td.removeClass("detail_close");
			td.addClass("detail_open");
			orders_table.close(nTr);
		}
	} );

	$(".delete_item").live("click", function()
	{
		var td = $(this);
		var id = td.find("div").text();

		if (!confirm("Вы действительно хотите изделие № " + id + " ?"))
			return;

		content_get("delete_item", {'id': id});

		var tr = td.closest("tr");
		var count = parseInt(tr.find("td:eq(5)").html());
		var price_sale = parseInt(tr.find("td:eq(4)").html());
		var order_id = tr.closest("table").prev("div").text();
		tr.remove();
		
		set_price(order_id, - (count * price_sale));
	});

	$(".edit_item").live("click", function()
	{
		var tr = $(this).closest("tr");

		tr.addClass("edit_row");

		var td_code = tr.find("td:eq(1)");
		td_code.html("<input type='text' name='code' value='" + td_code.text() + "' class='edit_item_code' /><div class='old'>" + td_code.text() + "</div>");

		var td_model = tr.find("td:eq(2)");
		td_model.html("<input type='text' name='model' value='" + td_model.text() + "' class='edit_item_model' /><div class='old'>" + td_model.text() + "</div>");

		var td_price_base = tr.find("td:eq(3)");
		td_price_base.html("<input type='text' name='price_base' value='" + td_price_base.text() + "' class='edit_item_price_base' /><div class='old'>" + td_price_base.text() + "</div>");

		var td_price = tr.find("td:eq(4)");
		td_price.html("<input type='text' name='price' value='" + td_price.text() + "' class='edit_item_price' /><div class='old'>" + td_price.text() + "</div>");

		var td_size = tr.find("td:eq(5)");
		var size = td_size.text();
		if (size == "не указан")
			size = "";
		td_size.html("<input type='text' name='size' value='" + size + "' class='edit_item_size' /><div class='old'>" + size + "</div>");

		var td_count = tr.find("td:eq(6)");
		td_count.html("<input type='text' name='count' value='" + td_count.text() + "' class='edit_item_count' /><div class='old'>" + td_count.text() + "</div>");

		var td_status = tr.find("td:eq(7)");
		var status = trim(td_status.text());
		td_status.html("<select name='status'>" +
						"<option value='ignored'" + (status == "не рассмотрено" ? " selected": "") + ">не рассмотрено</option>" +
						"<option value='process'" + (status == "в обработке" ? " selected": "") + ">в обработке</option>" +
						"<option value='not_size'" + (status == "нет размера" ? " selected": "") + ">нет размера</option>" +
						"<option value='not_available'" + (status == "нет в наличии" ? " selected": "") + ">нет в наличии</option>" +
						"<option value='cancel'" + (status == "отменен" ? " selected": "") + ">отменен</option>" +
						"<option value='return'" + (status == "возврат" ? " selected": "") + ">возврат</option>" +
						"<option value='end'" + (status == "выполнен" ? " selected": "") + ">выполнен</option>" +
					"</select><div class='old'>" + td_status.html() + "</div>");

		tr.find(".edit_column, .delete_column").hide();
		tr.find(".save_column, .cancel_column").show();
	});

	$(".cancel_item").live("click", function()
	{
		var tr = $(this).closest("tr");

		for (var i = 1; i <= 7; i++)
			cancel_edit_fill_td(tr, i);

		tr.find(".edit_column, .delete_column").show();
		tr.find(".save_column, .cancel_column").hide();
	})

	$(".add_item_link").live("click", function()
	{
		var that = $(this);
		var order_id = that.next("div").text();

		var table = that.closest("table");

		var code = table.find(".code").val();
		var size = table.find('.size').val();
		var count = table.find(".count").val();
		var model = table.find(".model").val();
		var price_base = table.find(".price_base").val();
		var price = table.find(".price").val();
		var xml = content_get("add_item", {'order_id': order_id, 'code': code, 'size': size, 'count': count, 'name': model, 'initial_price': price_base, 'price': price, 'status_manager': table.find(".status_manager").val()});

		if (xml.find("errors"))
		{
			var errors = xml.find("errors").text();
			if (errors != "")
			{
				alert(errors);
				return;
			}
		}
		$(xml.find("item").text()).prependTo("#order_items_" + order_id + "_table > tbody");
		set_price(order_id, parseInt(price) * parseInt(count));
	});
}

function cancel_edit_fill_td(tr, column_index)
{
	var td = tr.find("td:eq(" + column_index + ")");
	td.html(td.find(".old").html());
}

function fnFormatDetails (nTr)
{
	var aData = orders_table.get_data(nTr);
	var xml = content_get("get_order_items", {'id': aData[1]});

	var id = aData[1];

	var out =
		"<div class='order_items'>" +
		"<div style=='float: left; width: 400px'>" +
		"<table cellpadding=\"3\" cellspacing=\"0\" class=\"add_item_order\">" +
			"<thead><tr>" +
				"<td>код</td>" +
				"<td>кол-во</td>" +
				"<td>размер</td>" +
				"<td>цена отгрузки</td>" +
				"<td>цена продажи</td>" +
				"<td>модель</td>" +
				"<td>статус</td>" +
				"<td></td>" +
			"</tr></thead>" +
			"<tbody><tr>" +
				"<td><input type=\"text\" name=\"code\" class='code' /></td>" +
				"<td><input type=\"text\" name=\"count\" class='count' /></td>" +
				"<td><input type=\"text\" name=\"size\" class='size'/></td>" +
				"<td><input type=\"text\" name=\"price_base\" class='price_base'/></td>" +
				"<td><input type=\"text\" name=\"price\" class='price'/></td>" +
				"<td><input type=\"text\" name=\"model\" class='model'/></td>" +
				"<td><select name='status_manager' class='status_manager'>" +
						"<option value='ignored' selected>не рассмотрено</option>" +
						"<option value='process'>в обработке</option>" +
						"<option value='not_size'>нет размера</option>" +
						"<option value='not_available'>нет в наличии</option>" +
						"<option value='cancel'>отменен</option>" +
						"<option value='return'>возврат</option>" +
						"<option value='end'>выполнен</option>" +
					"</select>" +
				"<td><a href='#' class='add_item_link'>Добавить изделие</a><div class='hide'>" + id + "</div></td>" +
			"</tr></tbody>" +
		"</table>"+
		"<div class='hide'>" + id + "</div>" +
		"<table cellspacing=\"0\" cellpadding=\"0\" id=\"order_items_" + id + "_table" + "\" class='order_items_table'>" +
			"<thead>" +
				"<tr>" +
					"<th>ID</th>" +
					"<th>Код</th>" +
					"<th>Модель</th>" +
					"<th>Цена отгрузки</th>" +
					"<th>Цена продажи</th>" +
					"<th>Размер</th>" +
					"<th>Кол-во</th>" +
					"<th>Статус</th>" +
					"<th class=\"lastchild\"></th>" +
				"</tr>" +
			"</thead>" +
			"<tbody>" +
				xml.find("items").text() +
			"</tbody></table>" +
		"</div>" +
		"<div class='comment'>Комментарий: " + xml.find("comment").text() + "</div>" +
		"</div>";

	return out;
}

function set_price(order_id, diff_price)
{
	var trs = $("#orders_table tbody tr");

	for (var i = 0; i < trs.length; i++)
	{
		var tr = trs.eq(i);

		if (tr.find("td").length != 9)
			continue;

		var order_id2 = tr.find("td:eq(1)").text();
		if (order_id2 != order_id)
			continue;

		var price_td = tr.find("td:eq(4)");
		var new_price = parseInt(price_td.text()) + parseInt(diff_price);
		price_td.text(new_price);
		break;
	}
}

$(managerreport_init);

