/**
 * Pages
 * @uses jQuery Form
 * @uses Tree
 * @uses Trim
 * @uses ContextMenu
 * @uses TabKeys
 * @version 1.0.5
 */
var pages_tree;
var pages_menu;
var pages_readonly;

Loader.scripts(["jquery-form", "tree", "context_menu", "trim"], "pages_init");

function pages_init()
{
	$("#collapse").bind("click", function()
	{
		var menu = $("#pages_menu");

		if (menu.css("display") == "none")
			menu.css("display", "block");
		else
			menu.hide();

		pages_resize();
	});

	pages_tree = new Tree(
	{
		'container': "#menu",
		'root': "Дерево страниц",
		'lazy_url': content_url({'action': "get_menu"}),
		'state_url': content_url({'action': "get_state"}),
		'actions':
		{
			'click': pages_click,
			'dblclick': pages_dblclick
		}
	});

	pages_menu = new ContextMenu(
	{
		'menu': "context_menu",
		'bind_right': "#menu a",
		'show': function()
		{
			var allow_edit = pages_tree.is(this.owner, "editable");
			var locked = pages_tree.is(this.owner, "locked");

			this.toggle("rename", !locked);
			this.toggle("edit", allow_edit && !locked);
			this.toggle("expand", !allow_edit && !locked);
		},
		'handlers':
		{
			'expand': pages_expand,
			'add': pages_add_dialog,
			'edit': pages_action,
			'delete': pages_action,
			'rename': pages_action,
			'export': pages_action,
			'import': pages_action,
			'versions': pages_action
		}
	});

	$("#add_dialog").dialog(
	{
		title: "Введите имя новой страницы",
		width: 400,
		height: 180,
		minWidth: 250,
		minHeight: 180,
		maxHeight: 180,
		autoOpen: false,
		modal: true,
		buttons:
		{
			"Отмена": function()
			{
				$(this).dialog("close");
			},
			"Добавить": function()
			{
				var root = $("#dialog_root").val();
				var name = $("#dialog_name").val();

				var page_name = trim(root + "/" + name, "/");

				pages_add(page_name);

				$(this).dialog("close");

				window.scrollTo(0, 0);
			}
		}
	});
}

function pages_set_readonly(readonly)
{
	pages_readonly = readonly;
}

function pages_get_readonly()
{
	return pages_readonly;
}

function pages_set_create()
{
	$(document).ready(function()
	{
		$("#tabs").tabs("disable", 2);
		$("#content_apply").hide();
		$("#pages_editor_apply").hide();
	});
}

function pages_resize()
{
	$("#content, #params textarea").css({'height': "0px", 'width': "0px"});

	var body = $("body");
	var head_height = $(".head_shadow").height();
	var footer_height = $(".bottom_shadow").height();
	var tabs_width = $("#tabs").width();
	var conatiner_height = $(".content_conatiner").height();

	var content_height = body.height() - (head_height + conatiner_height + footer_height);
	if (content_height < 0)
		content_height = 0;

	var content_width = tabs_width - 52;
	if (content_width < 0)
		content_width = 0;

	$("#content").css({'height': content_height, 'width': content_width});

	var params_height = body.height() - (head_height + conatiner_height + footer_height);
	if (params_height < 0)
		params_height = 0;

	var params_width = tabs_width - 88;
	if (params_width < 0)
		params_width = 0;

	$("#params textarea").css({'height': params_height, 'width': params_width});
}

function pages_apply()
{
	$("#add_edit_form").ajaxSubmit(
	{
		beforeSubmit: forms_disable,
		success: forms_enable
	});
}

function pages_add(link_name)
{
	content_load({'action': "add"}, {'name': link_name});
	pages_tree.select();
}

function pages_restore(version_id)
{
	if (!confirm("Вы действительно хотите восстановить версию " + version_id + "?"))
		return false;

	content_load({'action': "restore"}, {'id': version_id});
	return false;
}

function pages_version(version_id)
{
	content_load({'action': "version"}, {'id': version_id});
	pages_tree.select();

	return false;
}

function pages_versions(type, value)
{
	var data = {};
	data[type] = value;

	content_load({'action': "versions"}, data, true);
	pages_tree.select();

	return false;
}

function pages_action(action)
{
	content_load({'action': action}, {'name': this.name});
	pages_tree.select(this);
}

function pages_expand()
{
	this.click();
}

function pages_add_dialog()
{
	$("#add_dialog").dialog("open");
	$("#dialog_root").val(this.name);
	$("#dialog_name").val("").focus();
	return false;
}

function pages_main()
{
	content_load({'action': "main"});
}

function pages_click(element)
{
	pages_action.call(element, "edit");
}

function pages_dblclick(element)
{
	if (!this.is(element, "editable"))
		return;

	pages_action.call(element, "edit");
}

function pages_update(added_pages, deleted_pages)
{
	for (var i = 0; i < added_pages.length; i++)
		pages_tree.add(added_pages[i], "/");

	for (i = 0; i < deleted_pages.length; i++)
		pages_tree.remove(deleted_pages[i], "/");

	if (added_pages.length == 1)
		pages_tree.select(added_pages[0]);
}

/**
 * Pages Content
 * @uses TabKeys
 * @version 1.0.1
 */
Loader.scripts(["tab_keys"], "pages_content_init");

function pages_content_init()
{
	$("#tabs").tabs(
	{
		show: pages_resize
	});

	var content = $("#content");
	var params = $("#params textarea");
	var content_apply = $("#content_apply");

	TabKeys.init(content);
	TabKeys.init(params);

	$("#name").bind("keyup", function()
	{
		if (this.value != $("input[name='orig_name']").val())
			content_apply.attr("disabled", true);
		else
			content_apply.attr("disabled", false);
	});

	$("#content_apply").bind("click", pages_apply);

	$(window).bind("resize", pages_resize);
}

/**
 * Pages Editor
 * @uses CKEditor
 * @uses CKFinder
 * @version 1.0.0
 */
CKEDITOR_BASEPATH = "/modules/ckeditor/";
Loader.scripts(["ckeditor", "ckfinder"], "pages_editor_init");

function pages_editor_init()
{
	var editor = CKEDITOR.replace("full_field");

	CKFinder.SetupCKEditor(editor, "/modules/ckfinder/");

	if (pages_get_readonly())
	{
		$("#pages_editor_save").hide();
		$("#pages_editor_apply").hide();
	}
	else
	{
		$("#pages_editor_save").bind("click", pages_editor_save);
		$("#pages_editor_apply").bind("click", pages_editor_apply);
	}

	$(".pages_editor_create").live("click", pages_editor_create);
	$("#pages_editor_hide").bind("click", pages_editor_hide);

	$(window).bind("resize", pages_editor_resize);
}

function pages_editor_create()
{
	var field_id = $(this).parent().find("textarea").attr("id");

	$("#pages_editor").attr("field_id", field_id);

	pages_editor_show();
	pages_editor_resize();

	var editor_instance = CKEDITOR.instances.full_field;

	if (editor_instance.mode != "source")
		editor_instance.setMode("source");

	editor_instance.setData($("#" + field_id).val());
	return false;
}

function pages_editor_show()
{
	$("#pages_table").hide();
	$("#pages_editor").show();
}

function pages_editor_hide()
{
	$("#pages_editor").hide();
	$("#pages_table").show();
}

function pages_editor_resize()
{
	var body = $("body");
	var width = body.width() - 20;
	var height = body.height() - 45;

	var editor_instance = CKEDITOR.instances.full_field;
	editor_instance.resize(width, height);
}

function pages_editor_save()
{
	pages_editor_set();
	pages_editor_hide();
	pages_apply();
}

function pages_editor_apply()
{
	pages_editor_set();
	pages_apply();
}

function pages_editor_set()
{
	var field_id = $("#pages_editor").attr("field_id");

	var orig_field = $("#" + field_id);
	if (!orig_field.length)
		return;

	var editor_instance = CKEDITOR.instances.full_field;
	orig_field.val(editor_instance.getData());
}

/**
 * Pages Params
 * @version 1.0.0
 */
function pages_params_init(param_names, param_contents)
{
	$(document).ready(function()
	{
		$("#params_add").bind("click", pages_params_add);
		$("#params_author").bind("click", pages_params_author);

		if (!pages_get_readonly())
		{
			$("#params").tabs(
			{
				tabTemplate: "<li><a href='#{href}'>#{label}</a> <span class='ui-icon ui-icon-close'>X</span></li>",
				show: pages_resize
			});

			$("#params ul.ui-tabs-nav").bind("dblclick", pages_params_add);
			$("#params span.ui-icon-close").live("click", pages_params_delete);
		}
		else
		{
			$("#params").tabs(
			{
				show: pages_resize
			});
		}

		for (var i = 0; i < param_names.length; i++)
		{
			pages_params_add(param_names[i]);

			$("#param_names_" + i).val(param_names[i]);
			$("#param_contents_" + i).val(param_contents[i]);
		}

		$("#params").tabs("select", 0);
	});
}

function pages_params_author()
{
	var user_id = $(this).attr("title");

	pages_versions("user_id", user_id);
}

function pages_params_delete()
{
	var confirmed = confirm("Вы действительно хотите удалить этот параметр?");
	if (!confirmed)
		return false;

	var params = $("#params");
	if (!params.length)
		return false;

	var tabs = $("li", params);

	var index = tabs.index($(this).parent());
	params.tabs("remove", index);

	if (params.tabs("length") != 0)
		return false;

	params.removeAttr("last_param_id");
	params.hide();
	return false;
}

function pages_params_update(param, param_id)
{
	var param_name = $(param).val();
	if (!param_name)
		param_name = "Параметр " + (parseInt(param_id) + 1);

	$("#params .ui-tabs-selected a").text(param_name);
}

function pages_params_add(param_name)
{
	var params = $("#params");
	if (!params.length)
		return;

	var last_param_id = params.attr("last_param_id");
	if (!last_param_id)
		last_param_id = 0;

	var param_id = last_param_id++;

	if (typeof param_name !== "string")
		param_name = "Параметр " + (parseInt(param_id) + 1);

	params.attr("last_param_id", last_param_id);
	params.tabs("add", "#params-" + param_id, param_name);

	var param_template = $("#param_template").html().replace(new RegExp("{param_id}", "g"), param_id);
	$("#params-" + param_id).append(param_template);

	var param_content = $("#param_contents_" + param_id);
	param_content.css("height", $("body").height() - 380);

	params.tabs("select", params.tabs("length") - 1);
	params.show();

	$("#param_names_" + param_id).bind("keyup change", function()
	{
		pages_params_update(this, param_id);
	}).focus();

	if (last_param_id == 1)
		pages_resize();
}

/**
 * Pages Versions
 * @uses OptionLinks
 * @version 1.0.0
 */
Loader.scripts(["option_links"], "pages_versions_init");

function pages_versions_init(user_id, session_id, final_value)
{
	if (final_value == "")
		final_value = "no";
	else
		final_value = "yes";

	var user_switches = ["all", "current"];

	if (user_id == "")
		user_id = "all";
	else if (user_id == session_id)
		user_id = "current";
	else
	{
		user_switches[user_switches.length] = "other";
		user_id = "other";
	}

	var versions_options = new OptionLinks(
	{
		'final':
		{
			'switches': ["no", "yes"],
			'default': final_value
		},
		'user':
		{
			'switches': user_switches,
			'default': user_id
		}
	});

	$("#path a").live("click", function()
	{
		pages_versions("name", $(this).attr("title"));
		return false;
	});

	var path = pages_versions_path($("#path").text());

	$("#path").html(path);
}

function pages_versions_path(path)
{
	var result = "<a href='#' title='/'>Корень</a>";

	if (path == "/")
		return result;

	var link_template = "<a href='#' title='{title}'>{name}</a>";
	var pieces = path.split("/");

	var current = "";
	for (var i = 0; i < pieces.length; i++)
	{
		if (current != "")
			current += "/";
		current += pieces[i];

		var code = link_template;
		code = code.replace("{name}", pieces[i]);
		code = code.replace("{title}", current);

		if (result != "")
			result += "/";
		result += code;
	}

	return result;
}

/**
 * Pages Filter
 * @version 1.0.0
 */
function pages_filter_init()
{
	$(document).ready(function()
	{
		$("#pages_filter").bind("change keyup", pages_filter_start);

		$("#clear_filter").bind("click", function()
		{
			var pages_filter = $("#pages_filter");

			pages_filter.val("");
			pages_filter_start.call(pages_filter[0]);
			return false;
		});
	});
}

function pages_filter_start()
{
	var filter_id = $(this).data("filter_id");
	if (filter_id)
		clearTimeout(filter_id);

	var set = function(instance)
	{
		return setTimeout(function()
		{
			pages_filter_apply.call(instance);
		}, 300);
	};

	$(this).data("filter_id", set(this));
}

function pages_filter_apply()
{
	var filter = $(this).val();
	if (filter.length < 2)
		filter = "";

	pages_tree.filter(filter);
}

/**
 * Pages Lists
 * @uses Inputs
 * @version 1.0.1
 */
Loader.scripts(["inputs"], "pages_lists_init");

function pages_lists_init()
{
	$("#check_all").live("click", Inputs.checkbox_toogle);
}

pages_init();
pages_editor_init();
pages_filter_init();
pages_lists_init();