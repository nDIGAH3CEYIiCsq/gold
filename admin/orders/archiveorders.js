/**
* ArchiveOrders
* @uses Sorted_table
* @uses ContextMenu
* @uses Inputs
* @version 1.0.2
*/
Loader.scripts(["sorted_table", "context_menu", "inputs"], "archive_orders_init");

var archive_orders_table;
var products_table;
var context_menu;

function archive_orders_init()
{
	archive_orders_table = new SortedTable("orders",
	{
		'columns':
		[
			{sClass: "center", bSortable: false},
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
			'bPaginate': false,
			'bFilter': false,
			'bLengthChange': false,
			'bInfo': false,
			'bServerSide': false,
			'bProcessing': false
		}
	});

	$("#orders_table").ready(archive_orders_menu_init);
	$("#check_all").live("click", Inputs.checkbox_toogle);
}

function archive_orders_menu_init()
{
	context_menu = new ContextMenu
	({
		'menu': "context_menu",
		'bind_right': "#orders_table tr",
		'handlers':
		{
			'show':	archive_orders_show,
			'delete': archive_orders_delete_current
		}
	});
}

function archive_orders_show()
{
	var url =  Ajax.nocache("?module=orders&action=archive_item" + "&id=" + archiveorders_get_menu_params().id);
	window.open(url);
}

function archiveorders_get_menu_params()
{
	var owner = context_menu.owner;

	if (owner.tagName == "A")
		owner = $(owner).closest("tr")[0];

	var tds = $("td", owner);
	var row = archive_orders_table.table.fnGetPosition(owner);
	var id = tds.eq(0).find("input").val();

	return {'row': row, 'id': id};
}

function archive_orders_delete()
{
	var checkboxes = $("#orders_table tbody input[type='checkbox']:checked");
	if (!checkboxes.length)
		return;

	if (!confirm("Вы действительно хотите удалить выбранные(ый) заказ(ы)?"))
		return;

	archive_orders_table.remove(checkboxes, "?module=orders&action=delete");
}

function archive_orders_delete_current()
{
	$("#orders_table tbody input[type='checkbox'][value='" + archiveorders_get_menu_params().id + "']").attr("checked", "checked");
	archive_orders_delete();
}

archive_orders_init();