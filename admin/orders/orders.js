/**
 * Orders
 * @uses Sorted_table
 * @uses ContextMenu
 * @uses Inputs
 * @version 1.0.2
 */
Loader.scripts(["sorted_table", "context_menu", "inputs", "tables"], "orders_init");

var orders_table;
var context_menu;

function orders_init()
{
	orders_table = new SortedTable("orders",
	{
		"aaSorting": [[ 1, "desc" ]],
		'columns':
		[
			{sClass: "center", bSortable: true},
			{sClass: "center", bSortable: true},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center lastchild"}
		],
		'options':
		{
			sAjaxSource: content_url({'action': "get_data"})
		}
	});

	$("#status").bind("change", function()
	{
		orders_table.table.fnFilter(this.value, 9);
	});


	$("#orders_table").ready(orders_menu_init);
	$("#check_all").live("click", Inputs.checkbox_toogle);
}

function orders_menu_init()
{
	context_menu = new ContextMenu
	({
		'menu': "context_menu",
		'bind_right': "#orders_table tr",
		'handlers':
		{
			'show': orders_edit,
			'delete': orders_delete_current
		}
	});
}

function orders_edit()
{
	window.open("?module=orders&action=item" + "&id=" + orders_get_menu_params().id);
}

function orders_delete()
{
	var checkboxes = $("#orders_table tbody input[type='checkbox']:checked");
	if (!checkboxes.length)
		return;

	if (!confirm("Вы действительно хотите удалить выбранные(ый) заказ(ы)?"))
		return;

        var ids = Inputs.checkbox_get_values(checkboxes);
        content_get("delete", {'ids': ids});
	orders_table.redraw();
}

function orders_delete_current()
{
	$("#orders_table tbody input[type='checkbox'][value='" + orders_get_menu_params().id + "']").attr("checked", "checked");
	orders_delete();
}

function orders_get_menu_params()
{
	var owner = context_menu.owner;

	if (owner.tagName == "A")
		owner = $(owner).closest("tr")[0];

	var tds = $("td", owner);
	var row = orders_table.table.fnGetPosition(owner);
	var id = tds.eq(0).find("input").val();

	return {'row': row, 'id': id};
}

orders_init();