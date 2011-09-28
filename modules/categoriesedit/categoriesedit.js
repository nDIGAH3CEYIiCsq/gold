/**
 * CategoriesEdit
 * @uses Categories
 * @version 1.0.2
 */
Loader.scripts(["categories", "prototype", "context_menu"]);


var CategoriesEdit = function(options)
{
	CategoriesEdit.parent.constructor.call(this, options);

	CategoriesEdit.prototype.menu_init	= categories_menu_init;
	CategoriesEdit.prototype.dialogs_init	= categories_dialogs_init;

	CategoriesEdit.prototype.dialog_add	= categories_dialog_add;
	CategoriesEdit.prototype.dialog_rename	= categories_dialog_rename;
	CategoriesEdit.prototype.dialog_delete	= categories_dialog_delete;
	CategoriesEdit.prototype.load_deleting	= categories_load_deleting;

	CategoriesEdit.prototype.get_menu_node	= categories_get_menu_node;

	this.menu_init();
	this.dialogs_init();
}

prototype_extend(CategoriesEdit, Categories);

function categories_menu_init()
{
	var init1 = function (instance)
	{
		instance.context_menu = new ContextMenu(
		{
			'menu': "context_menu",
			'bind_right': ".ui-dynatree-document,.ui-dynatree-folder",
			'handlers':
			{
				'add': function()
				{
					var dialog = $("#add_dialog");
					dialog.find(".errors").empty();
					dialog.dialog("open");
				},
				'rename': function()
				{
					var node = instance.get_menu_node();
					$("#rename_dialog_name").val(node.data.title);
					var dialog = $("#rename_dialog");
					dialog.find(".errors").empty();
					dialog.dialog("open");
				},
				'delete': function()
				{
					var node = instance.get_menu_node();
					instance.load_deleting($("#delete_dialog"));

					$("#delete_dialog_name").text(node.data.title);
					$("#delete_dialog").dialog("open");
				}
			}
		});
	};

	init1(this);
}

function categories_get_menu_node()
{
	var owner = $(this.context_menu.owner);
	var id = owner.attr("id");
	var menu_node = this.get_node(id);

	return menu_node;
}

function categories_dialogs_init()
{
	$("#add_dialog").dialog(
	{
		title: "Введите имя новой категории",
		width: 350,
		autoOpen: false,
		autoResize: true,
		modal: true,
		buttons:
		{
			"Отмена": function()
			{
				$(this).dialog("close");
			},
			"Добавить": function (instance)
			{
				return function()
				{
					instance.dialog_add($(this));
				}
			}(this)
		}
	});

	$("#rename_dialog").dialog(
	{
		title: "Введите новое имя категории",
		width: 350,
		autoOpen: false,
		autoResize: true,
		modal: true,
		buttons:
		{
			"Отмена": function()
			{
				$(this).dialog("close");
			},
			"Сохранить": function (instance)
			{
				return function()
				{
					instance.dialog_rename($(this));
				}
			}(this)
		}
	});

	$("#delete_dialog").dialog(
	{
		title: "Удаление категории",
		width: 350,
		autoOpen: false,
		autoResize: true,
		modal: true,
		buttons:
		{
			"Отмена": function()
			{
				$(this).dialog("close");
			},
			"Удалить": function (instance)
			{
				return function ()
				{
					instance.dialog_delete($(this));
				}
			}(this)
		}
	});
}

function categories_dialog_add(dialog)
{
	dialog.find(".errors").empty();

	var name = $("#add_dialog_name").val();

	var node = this.get_menu_node();
	var category_id = this.get_key(node);

	var xml = Ajax.post("?module=categories&action=add", {'parent_id': category_id, 'name': name});
	if (Ajax.check_error(xml, {'categories': 'add_errors'}))
		return;

	var id = xml.find("id").text();
	node.addChild({'title': name, 'key': id});

	dialog.dialog("close");
}

function categories_dialog_rename(dialog)
{
	dialog.find(".errors").empty();

	var name = $("#rename_dialog_name").val();

	var node = this.get_menu_node();
	var category_id = this.get_key(node);

	var xml = Ajax.post("?module=categories&action=rename", {'id': category_id, 'name': name});
	if (!Ajax.check_error(xml, {'categories': 'rename_errors'}))
		return;

	node.data.title = name;
	node.render();

	dialog.dialog("close");
}

function categories_dialog_delete(dialog)
{
	var node = this.get_menu_node();
	var category_id = this.get_key(node);
	var xml = Ajax.post("?module=categories&action=delete", {'id': category_id});
	if (!Ajax.check_error(xml, {"categories": "delete_errors"}))
		return;

	node.remove();

	dialog.dialog("close");
}

function categories_load_deleting()
{
	var node = this.get_menu_node();
	var category_id = this.get_key(node);
	
	var xml = Ajax.post("?module=categories&action=children", {'id': category_id, 'all': 1});
	if (!Ajax.check_error(xml, {"categories": "delete_errors"}))
		return;

	var deleting = this.gen_tree(xml, node.data.title);
	var list = Arrays.make_list(deleting);

	$("#delete_dialog_names").html(list);
}