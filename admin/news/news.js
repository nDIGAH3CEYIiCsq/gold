/**
 * News
 * @uses jQuery dynatree
 * @uses Ajax
 * @uses SortedTable
 * @uses ContextMenu
 * @uses Tables
 * @uses Validator
 * @version 1.0.3
 */
Loader.scripts(["ajax", "sorted_table", "option_links", "validator"], "news_init");

var news_options;

function news_init()
{
	$("#message").dialog(
	{
		title: "Ошибка",
		width: 400,
		autoOpen: false,
		modal: true,
		autoResize: true,
		buttons:
		{
			"Отмена": function()
			{
				$(this).dialog("close");
			}
		}
	});

	$("#category_dialog").dialog(
	{
		title: "Добавление новой категории",
		width: 400,
		autoOpen: false,
		modal: true,
		resizable: false,
		buttons:
		{
			"Отмена": function()
			{
				$(this).dialog("close");
			},
			"Добавить": news_add_category_submit
		}
	});
	news_init_data();
	new_btns_init();
	news_status_change_init();
	news_init_links();
}

function news_init_links()
{
	$(".news_delete_link").live("click", function()
	{
		var element = $(this);
		var tr = element.closest("tr");
		var name = tr.find(".news_name").text();
		var category = tr.find(".news_category").text();
		content_get("delete", {'name': name, 'category': category});
		tr.remove();
		news_options.set("image", "off");

		return false;
	});

	$(".news_edit_link").live("click", function()
	{
		var element = $(this);
		var tr = element.closest("tr");
		var name = tr.find(".news_name").text();
		var category = tr.find(".news_category").text();
		var data = content_get("view", {'name': name, 'category': category});

		var news = data.find("news");

		var title = news.attr("NewsTitle");
		$("#title").val(title);
		if (title != "" && typeof title != "undefined")
			news_options.set("title", "on");

		var name_page = news.attr("name");
		$("#name").val(name_page);
		$("#name_old").val(name_page);
		$("#name_old").val(name_page);

		category = news.attr("NewsCategory");
		$("#category").val(category);
		$("#category_old").val(category);

		var short_text = news.attr("NewsShort");
		var text = news.attr("text");

		var user_only_short = news.attr("UseOnlyShort");
		if (user_only_short != "" && typeof user_only_short == "undefined")
		{
			news_options.set("text","on");
			$("#short_text").val(short_text);
			$("#text").val(text);
			$("#use_text").val("1");
		}
		else
			$("#short_text").val(text);

		var img = news.attr("img");
		if (img != "" && typeof img != "undefined")
		{
			news_options.set("image", "on");
			$("#image_exist").html(img);
		}
		else
			news_options.set("image", "off");

		$("#image_div_on").hide();

		news_set_action_save();

		$("#add_form").show();
		$("#news_table").hide();

		return false;
	})

	$("#files_link_on").bind("click", function()
	{
		var files = $("#files");
		var files_item = $("#file_item").html();
		news_add_element(files, files_item, "files", "files_names");
		return false;
	})

	$("#link_link_on").bind("click", function()
	{
		var links = $("#links");
		var link_item = $("#link_item").html();
		news_add_element(links, link_item, "links", "links_names");
		return false;
	})
}

function new_btns_init()
{
	$("#news_add_btn").bind("click", function()
	{
		$("#name").val("");
		$("#short_text").val("");
		$("#text").val("");
		news_options.set("text","off");
		news_options.set("title","off");
		news_options.set("image","off");
		$("#add_form").show();
		$("#news_table").hide();
	});
	$("#news_add_category_btn").bind("click", news_add_category);
	$("#news_cancel_btn").bind("click", news_cancel);
	$("#news_img_delete_link").live("click", function()
	{
		news_options.set("image", "off");
		$("#image_div_on").hide();
		$(this).closest("div").remove();
		$("#image").val("");
		return false;
	});
}

function news_status_change_init()
{
	var news_statuses = $(".news_status");
	for (var i = 0; i < news_statuses.length; i++)
	{
		var news_status = news_statuses.eq(i);
		news_status.bind("click", function()
		{
			var icon = $(this).children("div.ui-icon");
			var display = icon.hasClass("ui-icon-unlocked");
			var page_name = news_status.prev(".news_name").text();
			var page_category = news_status.prev(".news_category").text();
			var answer = content_get("set_status", {'name': page_name, 'category': page_category, 'display': !display}, "html");
			if (answer != "")
			{
				news_show_mesage(answer);
				return false;
			}

			news_set_status(icon, display);
			return false;
		});
	}
}

function news_set_status(icon, display)
{
	if (display)
	{
		icon.removeClass("ui-icon-unlocked").addClass("ui-icon-locked");
		icon.attr("title", "Статья скрыта, показать?");
	}
	else
	{
		icon.removeClass("ui-icon-locked").addClass("ui-icon-unlocked");
		icon.attr("title", "Статья открыта, скрыть?");
	}
}

function news_show_mesage(message)
{
	$("#message").html(message);
	$("#message").dialog("open");
}

function news_init_data()
{
	news_options = new OptionLinks(
	{
		'title':
		{
			'default': "off",
			'handler': function(state)
			{
				$("#use_title").val(state == "on"? 1:0);
				return true;
			}
		},
		'text':
		{
			'default': "off",
			'handler': function(state)
			{
				$("#use_text").val(state == "on"? 1:0);
				return true;
			}
		},
		'image':
		{
			'default': "on",
			'handler': function(state)
			{
				$("#use_image").val(state == "on"? 1:0);
				$("#image_exist").html("");

				return true;
			}
		}
	});

	var categories = content_get("get_categories", {}, "html");
	$("#category").html(categories);
}

function news_cancel()
{
	$("#add_form").hide();
	$("#news_table").show();
	news_set_action_add();

}

function news_show_input(input_name, span_text_off, span_text_on)
{
	var input = $("#" + input_name);
	var span = $("#span_" + input_name);

	input.toggle();

	if (input.filter(":visible").length)
		span.html(span_text_on);
	else
		span.html(span_text_off);
}

function news_add_category()
{
	$("#new_category").val("");
	$("#category_dialog").dialog("open");
}

function news_add_category_submit()
{
	var category = $("#category");
	var category_value = $("#new_category").val();
	category_value = trim(category_value);
	category_value = category_value.replace(new RegExp("/", "g"), "");

	if (category_value == "")
		return;

	content_get("add_category", {'category': category_value});
	category.append("<option value='" + category_value + "'>" + category_value + "</option>");
	var count = category.find("option").length;
	category.attr("selectedIndex", count - 1);

	$(this).dialog("close");
}

function news_add_element(element,template, elements_names, caption_names)
{
	var count = element.children().length;
	var new_name_element = elements_names+ "[" + count + "]";
	var new_name_caption = caption_names+ "[" + count + "]";
	template = template.replace("[element]", new_name_element);
	template = template.replace("[caption]", new_name_caption);
	element.append(template);
}

function news_set_action_save()
{
	var form = $("#news_form");
	form[0].action = "?module=news&action=save";
	$("#news_submit_add_btn").text("Сохранить");
}

function news_set_action_add()
{
	var form = $("#news_form");
	form[0].action = "?module=news&action=add";
	$("#news_submit_add_btn").text("Добавить");
}

$(document).ready(news_init);