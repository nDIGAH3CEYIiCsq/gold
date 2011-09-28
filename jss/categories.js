Loader.scripts(["arrays"]);

var categories = [21, 11, 12, 15, 115, 114, 20, 19, 18, 17, 16, 167, 166];

function categories_init()
{
	$(".catalog .expander").bind("click", function()
	{
		var element = $(this);

		if (element.hasClass("empty"))
			return false;

		if (element.hasClass("selected"))
		{
			var category = element.closest("div.category");
			close_root_category(category);

			return false;
		}
		if (element.hasClass("not-selected"))
		{
			category = element.closest("div.category");
			open_root_category(category);

			return false;
		}

		return false;
	});

	$(".category .name").bind("click", function()
	{
		var element = $(this);
		var category = element.closest(".category");
		if (category.find(".name-container").hasClass("selected"))
			return true;

		open_root_category(category);
		return true;
	})

	var children = categories_children();
	children.bind("click", function()
	{
		children.removeClass("selected");
		var child = $(this);
		categories_select_child(child);
		$(".category .name-container").removeClass("selected");
		child.closest(".category").find(".name-container").addClass("selected");
		return true;
	});

	var dec_location = decode(unescape(location.href));
	var dec_location2 = unescape(location.href);
	if (categories_activate(dec_location))
		return;
	else if (categories_activate(dec_location2))
		return;
}

function open_root_category(category)
{
	$(".category .name-container").removeClass("selected");
	category.find(".name-container").addClass("selected");

	var expander = category.find(".expander");
	if (expander.hasClass("empty"))
		return;
	expander.removeClass("not-selected");
	expander.addClass("selected")

	category.find("div.children").show();
}

function close_root_category(category)
{
	var expander = category.find(".expander");
	expander.removeClass("selected");
	expander.addClass("not-selected")
	category.find(".children").hide();
}

function categories_activate(current_location)
{
	var values = current_location.split("/");
	if (values.length < 5)
		return false;

	if ((values[3] == "Каталог") || (values[3] == "Изделие"))
	{	categories_activate_by_id();
		return true;
	}

	return false;
}

function categories_activate_by_id(parent_force_id)
{
	var parent_id = $("#category-parent-id").text();

	if (typeof parent_force_id != "undefined")
		parent_id = parent_force_id;

	if (parent_id == "")
		return false;

	var child_id = $("#category-child-id").text();
	if (child_id == "")
		child_id = "0";

	var parents_ids = $(".category .id");
	for (var i = 0; i < parents_ids.length; i++)
	{
		var parent = parents_ids.eq(i);

		if (parent.text() != parent_id)
			continue;		

		var category = parent.closest(".category");
		category.find(".name-container").addClass("selected");
		if (Arrays.has(parent_id, categories))
			return true;

		category.find(".expander").addClass("selected");
		category.find(".children").show();

		var children = category.find(".children A");
		for (var j = 0; j < children.length; j++)
		{
			var child = children.eq(j);
			if (child.next("div").text() != child_id)
				continue;

			categories_select_child(child);
			break;
		}
		return true;
	}

	return false;
}

function categories_children(parent)
{
	if (typeof parent != "undefined")
		return parent.find("A");
	else
		return $(".category .children A");
}

function categories_select_child(child)
{
	child.addClass("selected");
}

function decode(utftext)
{
	var string = "";
	var i = 0;
	var c = c1 = c2 = 0;

	while ( i < utftext.length )
	{
		c = utftext.charCodeAt(i);

		if (c < 128)
		{
			string += String.fromCharCode(c);
			i++;
		}
		else if((c > 191) && (c < 224))
		{
			c2 = utftext.charCodeAt(i+1);
			string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
			i += 2;
		}
		else
		{
			c2 = utftext.charCodeAt(i+1);
			c3 = utftext.charCodeAt(i+2);
			string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
			i += 3;
		}
	}
	return string;
}

$(document).ready(categories_init);