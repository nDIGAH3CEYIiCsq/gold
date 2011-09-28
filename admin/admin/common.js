/**
 * Title
 * @version 1.0.2
 */
function title_update(title)
{
	document.title = title;
	$(".title").text(title);
}

/**
 * Buttons
 * @version 1.0.2
 */
function buttons_init()
{
	$(document).ready(function()
	{
		$("button").button();
	});
}

/**
 * Dialogs
 * @version 1.0.2
 */
function dialogs_init()
{
	$(".ui-dialog").live("keydown", function(event)
	{
		if (event.keyCode != 13)
			return true;

		var buttons = $(this).find(".ui-dialog-buttonpane").find("button");
		if (buttons.length <= 1)
			return true;

		buttons.eq(buttons.length - 1).click();
		return false;
	});
}

/**
 * Content
 * @uses Ajax
 * @version 1.0.5
 */
Loader.scripts(["ajax"]);

var content_query = {'get': {}, 'post': {}};
var content_module;

function content_init(module)
{
	content_module = module;
}

function content_url(params, ignore_nocache)
{
	params['module'] = content_module;

	var url = $.param(params);
	if (url != "")
		url = "?" + url;

	if (ignore_nocache)
		return url;

	return Ajax.nocache(url);
}

function content_change_page(page)
{
	content_load({'page': page}, {}, true);
}

function content_action(action, get)
{
	if (typeof get == "undefined")
		get = {};

	get['action'] = action;

	var url = content_url(get);

	location.href = url;
}

function content_get(action, post, data_type)
{
	var url = content_url({'action': action}, true);

	return Ajax.post(url, post, data_type);
}

function content_load(get, post, merge)
{
	if (merge)
	{
		get = $.extend(content_query['get'], get);
		post = $.extend(content_query['post'], post);
	}

	content_query['get'] = get;
	content_query['post'] = post;

	$("#pages_content").html("Загрузка страницы...");

	var url = content_url(get);

	$.post(url, post,
		function(data)
		{
			$("#pages_content").html(data);
		},
		"html"
	);
}

/**
 * Forms
 * @version 1.0.2
 */
function forms_init()
{
	$(document).ready(function()
	{
		$("FORM.pages_form").ajaxForm(
		{
			target: "#pages_content",
			beforeSubmit: forms_disable,
			success: forms_enable
		});

		$("FORM.pages_form_files").ajaxForm(
		{
			beforeSubmit: forms_disable,
			success: forms_set_xml,
			iframe: true,
			dataType: "xml"
		});
	});
}

function forms_disable()
{
	$("FORM.form button").attr("disabled", true);
	$("#edit_box button").attr("disabled", true);
}

function forms_enable()
{
	$("FORM.form button").attr("disabled", false);
	$("#edit_box button").attr("disabled", false);
}

function forms_set_xml(data)
{
	var content = $(data).find("content");
	$("#pages_content").html(content.text());
}