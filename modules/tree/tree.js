/**
 * Tree
 * @uses Ajax
 * @version 1.0.5
 */
Loader.scripts(["ajax"]);

var Tree = function(options)
{
	// Public
	Tree.prototype.get			= tree_get;
	Tree.prototype.add			= tree_add;
	Tree.prototype.remove			= tree_remove;
	Tree.prototype.is			= tree_is;
	Tree.prototype.state			= tree_state;
	Tree.prototype.toggle			= tree_toggle;
	Tree.prototype.show			= tree_show;
	Tree.prototype.hide			= tree_hide;
	Tree.prototype.select			= tree_select;
	Tree.prototype.filter			= tree_filter;

	// Private
	Tree.prototype.init			= tree_init;
	Tree.prototype.lazy			= tree_lazy;
	Tree.prototype.action			= tree_action;
	Tree.prototype.click			= tree_click;
	Tree.prototype.insert			= tree_insert;
	Tree.prototype.load			= tree_load;
	Tree.prototype.sub			= tree_sub;

	this.init(options);
}

function tree_init(options)
{
	this.options		= options;
	this.actions		= {};
	this.filter_value	= "";
	this.template		= "<div><a href='#' name='{name}'>{caption}</a><div class='sub'></div></div>";

	if (typeof this.options['container'] == "undefined")
		throw "Need container option";

	if (typeof this.options['actions'] != "undefined")
		this.actions = this.options['actions'];

	if (typeof this.options['template'] != "undefined")
		this.template = this.options['template'];

	this.container = $(this.options['container']);

	if (typeof this.options['root'] != "undefined")
	{
		var root_data = {
			'name': "",
			'caption': this.options['root'],
			'state': {'locked': true}
		};

		this.root = this.insert(this.container, root_data, "append");
	}
	else
		this.root = this.container;

	this.lazy();

	var hrefs = this.container.find("a");

	var bind = function (instance, elements)
	{
		elements.live("click", function ()
		{
			instance.click(this);
			return false;
		});

		elements.live("dblclick", function ()
		{
			instance.action("dblclick", this);
			return false;
		});
	};

	bind(this, hrefs);
}

function tree_filter(filter)
{
	if (typeof filter == "undefined")
		return this.filter_value;

	if (this.filter_value == filter)
		return true;

	this.filter_value = filter;
	this.lazy();

	return true;
}

function tree_get(name)
{
	var links = this.container.find("a[name='" + name + "']");

	for (var i = 0; i < links.length; i++)
	{
		var link = links.eq(i);

		if (link.attr("name") == name)
			return link;
	}

	return null;
}

function tree_add(page, delimeter)
{
	var link, cur_name = "", parent_div = this.container;

	var pieces = page.split(delimeter);
	for (var piece = 0; piece < pieces.length; piece++)
	{
		if (cur_name != "")
			cur_name += delimeter;
		cur_name += pieces[piece];

		link = this.get(cur_name);
		if (link != null)
		{
			if (piece == pieces.length - 1)
			{
				if (!this.is(link, "editable"))
					this.state(link, "editable", true);
				continue;
			}

			parent_div = this.sub(link);

			if (this.is(link, "final"))
			{
				this.state(link, "final", false);
				this.show(parent_div);
				continue;
			}

			if (parent_div.children().length != 0)
			{
				this.show(parent_div);
				continue;
			}

			this.lazy(link[0], false);
			continue;
		}

		var found_div = null;

		var divs = parent_div.children("div");
		for (var k = 0; k < divs.length; k++)
		{
			var div = divs.eq(k);

			var name = div.children("a").text();
			if (name.localeCompare(pieces[piece]) <= 0)
				continue;

			found_div = div;
			break;
		}

		var data = {
			'name':		cur_name,
			'caption':	pieces[piece]
		};

		if (piece == pieces.length - 1)
			data['state'] = {'final': true, 'editable': true};

		if (found_div != null)
			parent_div = this.insert(found_div, data, "before");
		else
			parent_div = this.insert(parent_div, data, "append");
	}
}

function tree_remove(page, delimeter)
{
	if (typeof this.options['state_url'] == "undefined")
		return;

	var pieces = page.split(delimeter);
	for (; pieces.length != 0; pieces.length--)
	{
		var cur_name = pieces.join("/");

		var link = this.get(cur_name);
		if (!link.length)
			continue;

		var state = Ajax.post(this.options['state_url'], {'name': cur_name});

		if ($("final", state).length)
		{
			this.state(link, {'final': true, 'opened': false});
			break;
		}

		if ($("exist", state).length)
		{
			this.state(link, "editable", false);
			break;
		}

		var div = link.parent();
		var sub = div.parent();

		div.remove();

		while (sub.length != 0)
		{
			link = sub.prev("a");
			if (this.is(link, "locked"))
				break;

			if (sub.children("div").length)
				break;

			if (this.is(link, "editable"))
			{
				this.state(link, {'final': true, 'opened': false});
				break;
			}

			div = link.parent();
			sub = div.parent();

			div.remove();
		}

		break;
	}
}

function tree_is(element, name)
{
	return this.state(element, name);
}

function tree_state(element, name, state)
{
	element = $(element);

	var names = {};
	if (typeof name == "string")
	{
		if (typeof state == "undefined")
			return element.hasClass("tree_item_" + name);

		names[name] = state;
	}
	else
		names = name;

	for (var key in names)
	{
		if (names[key])
			element.addClass("tree_item_" + key);
		else
			element.removeClass("tree_item_" + key);
	}

	return this;
}

function tree_lazy(element, async)
{
	if (typeof this.options['lazy_url'] == "undefined")
		return;

	var name = "", container = this.root;
	if (typeof element != "undefined")
	{
		name = element.name;
		container = this.sub(element)
	}

	if (typeof async == "undefined")
		async = true;

	container.empty();

	var lazy = function(instance, prefix, element)
	{
		$.ajax({
			'type': "POST",
			'url': instance.options['lazy_url'],
			'data':
			{
				'prefix': prefix,
				'filter': instance.filter()
			},
			'async': async,
			'success': function(data)
			{
				var answer = $("answer", data);

				instance.load(element, answer);
				instance.show(element);
			},
			'dataType': "xml"
		});
	};

	lazy(this, name, container);
}

function tree_load(container, xml)
{
	var elements = xml.children("element");
	if (!elements.length)
		return;

	for (var i = 0; i < elements.length; i++)
	{
		var element = elements.eq(i);

		var data = {
			'name':		element.attr("name"),
			'caption':	element.attr("caption"),
			'state':
			{
				'final':	element.attr("final"),
				'editable':	element.attr("editable")
			}
		};

		var current = this.insert(container, data, "append");

		this.load(current, element);
	}

	this.show(container);
}

function tree_insert(container, data, type)
{
	var code = this.template;
	code = code.replace("{name}", data['name']);
	code = code.replace("{caption}", data['caption']);

	var div = $(code);
	var href = div.find("a");

	if (typeof data['state'] != "undefined")
		this.state(href, data['state']);

	switch (type)
	{
		case "append":
			container.append(div);
			break;
		case "before":
			container.before(div);
			break;
	}

	return this.sub(href);
}

function tree_action(action, element)
{
	if (typeof this.actions[action] == "undefined")
		return;

	this.actions[action].call(this, element);
}

function tree_click(element)
{
	var link = $(element);

	if (this.is(link, "locked"))
		return true;

	if (this.is(link, "final"))
	{
		this.action("click", element);
		return true;
	}

	var div = this.sub(link);
	if (!div.length)
		return false;

	if (div.children("div").length)
	{
		this.toggle(div);
		return false;
	}

	this.lazy(element);
	return false;
}

function tree_toggle(element)
{
	var link = element.prev("a");
	if (this.is(link, "opened"))
		this.hide(element);
	else
		this.show(element);
}

function tree_show(element)
{
	element.slideDown("fast");

	var link = element.prev("a");
	if (!this.is(link, "opened"))
		this.state(link, "opened", true);
}

function tree_hide(element)
{
	element.slideUp("fast");

	var link = element.prev("a");
	if (this.is(link, "opened"))
		this.state(link, "opened", false);
}

function tree_select(element)
{
	if (typeof element == "string")
		element = this.get(element);

	var hrefs = this.container.find("a");
	this.state(hrefs, "selected", false);

	if (typeof element == "undefined")
		return;

	this.state(element, "selected", true);
}

function tree_sub(element)
{
	return $(element).next("div.sub");
}