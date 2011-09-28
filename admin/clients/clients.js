/**
 * Clients
 * @uses Sorted_table
 * @uses ContextMenu
 * @uses Inputs
 * @version 1.0.1
 */

Loader.scripts(["inputs", "sorted_table", "context_menu", "tables"], "clients_init");
var clients_table;
var context_menu;

function clients_init()
{
	clients_table = new SortedTable("clients",
	{
		'columns':
		[
			{sClass: "center"},
			{sClass: "left"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "left"},
			{sClass: "left"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center lastchild"}
		],
		'click_check': true,
		'options':
		{
			sAjaxSource: content_url({'action': "get_data"})
		}/*,
		'filters':
		{
			'accept': 8
		}*/
	});

	//$("#clients_table").ready(clients_menu_init);
	//$("#check_all").live("click", Inputs.checkbox_toogle);
	$("#delete_btn").bind("click",clients_delete);
}

function clients_menu_init()
{
	context_menu = new ContextMenu
	({
		'menu': "context_menu",
		'bind_right': "#clients_table tr",
		'handlers':
		{
			'delete': clients_delete_current,
			'edit'	: clients_edit
		}
	});
}

function clients_edit()
{
	window.open("?module=clients&action=edit" + "&id=" + clients_get_menu_params().id);
}

function clients_delete()
{
	if (!confirm("Вы действительно хотите удалить выбранных клиентов?"))
		return;

	var checkboxes = $("#clients_table tbody input[type='checkbox']:checked");
	clients_table.remove(checkboxes, "?module=clients&action=delete");
}

function clients_delete_current()
{
	var menu_params = clients_get_menu_params();
	$("#clients_table tbody input[type='checkbox'][value='" + menu_params.id + "']").attr("checked", "checked");
	clients_delete();
}

function clients_get_menu_params()
{
	var owner = context_menu.owner;

	if (owner.tagName == "A")
		owner = $(owner).closest("tr")[0];

	var tds = $("td", owner);
	var row = clients_table.table.fnGetPosition(owner);
	var id = tds.eq(0).find("input").val();

	return {'row': row, 'id': id};
}

$(document).ready(clients_init);