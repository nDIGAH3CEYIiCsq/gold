/**
 * Order
 * @uses Sorted_table
 * @uses ContextMenu
 * @uses Inputs
 * @version 1.0.2
 */
Loader.scripts(["sorted_table", "context_menu", "inputs", "numbers"], "order_init");

var products_table;
var context_menu;

function order_init()
{
	products_table = new SortedTable("products",
	{
		'columns':
		[
			{sClass: "center", bSortable: false},
			{sClass: "left"},
			{sClass: "left"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center lastchild"}
		],
		'options':
		{
			'bPaginate': false,
			'bFilter': false,
			'bLengthChange': false,
			'bInfo': false,
			'bServerSide': false,
			'bProcessing': false
		},
		'click_check': true
	});

	order_init_btns();
	order_dialogs_init();
	order_menu_init();
}

function order_init_btns()
{
	$("#button_delete").bind("click", order_delete);
	$("#delete_prods").bind("click", order_delete_prod);
}

function order_menu_init()
{
	context_menu = new ContextMenu
	({
		'menu': "context_menu",
		'bind_right': "#products_table tr",
		'handlers':
		{
			'edit_count': order_edit_count,
			'delete': order_delete_prod_current
		}
	});
}

function order_dialogs_init()
{
	$("#edit_dialog").dialog(
	{
		title: "Введите новое кол-во товаров",
		width: 350,
		autoOpen: false,
		autoResize: true,
		modal: true,
		buttons:
		{
			"Отмена": function() {$(this).dialog("close");},
			"Сохранить": function ()
			{
				return function() {order_edit_count_apply($(this));}
			}(this)
		}
	});
}

function order_delete()
{
	var order_id = $("#order_id").val();
	Ajax.post("?module=orders&action=delete", {'ids': [order_id]});
	window.close();
}

function order_delete_prod_current()
{
	$("#products_table tbody input[type='checkbox'][value='" + order_get_menu_params().id + "']").attr("checked", "checked");
	order_delete();
}

function order_delete_prod()
{
	if (!confirm("Вы действительно хотите удалить выбранные товары?"))
			return;

	var checkboxes = $("#products_table tbody input[type='checkbox'][name='product_id']:checked");
	products_table.remove(checkboxes, "?module=orders&action=delete_products", {'order_id': $("#order_id").val()});
}

function order_edit_count()
{
	var params = order_get_menu_params();
	var items = order_get_menu_params().id.toString().split("_");
	$("#product_id").val(items[0]);
	$("#size").val(params.size);
	$("#edit_dialog").dialog("open");
}

function order_edit_count_apply()
{
	Validator.reset();
	var element = $("#edit_dialog_сount");
	var count = element.val();
	if (!is_int_positive(count))
	{
		Validator.show_error(element, "Не корректно число");
		return;
	}
	var order_id = $("#order_id").val();
	var product_id = $("#product_id").val();
	var size = $("#size").val();

	$("#edit_dialog").dialog("close");

	var xml = Ajax.post("?module=orders&action=set_count", {'id': order_id, 'count': count, 'product_id': product_id, 'size': size});
	products_table.update(xml, product_id)
	order_recalc_price();
}

function order_recalc_price()
{
	var prices = $(".price");
	var result = 0;
	for (var i = 0; i < prices.length; i++)
		result += parseFloat(prices.eq(i).text());

	$("#price").text(result);
}

function order_get_menu_params()
{
	var owner = context_menu.owner;

	if (owner.tagName == "A")
		owner = $(owner).closest("tr")[0];

	var tds = $("td", owner);
	var row = products_table.table.fnGetPosition(owner);
	var id = tds.eq(0).find("input").val();
	var size = tds.eq(3).text();
	return {'row': row, 'id': id, 'size': size};
}

order_init();