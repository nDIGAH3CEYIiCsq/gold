/**
 * OptionLinks
 * @version 1.0.2
 */
Loader.styles(["option_links"]);

var OptionLinks = function(links)
{
	// Public
	OptionLinks.prototype.get	= option_links_get;
	OptionLinks.prototype.set	= option_links_set;
	OptionLinks.prototype.change	= option_links_change;

	// Private
	OptionLinks.prototype.init	= option_links_init;

	this.init(links);
}

function option_links_init(links)
{
	this.links = links;
	this.options = {};

	for (var prefix in this.links)
	{
		var data = this.links[prefix];

		if (typeof data['switches'] != "object")
			data['switches'] = ["on", "off"];
		if (typeof data['margins'] != "object")
			data['margins'] = [];
		if (typeof data['default'] != "string")
			data['default'] = "on";
		if (typeof data['handler'] != "function")
			data['handler'] = null;

		data['containers'] = $([]);

		var switches = data['switches'];
		var margins = data['margins'];

		data['divs'] = {};
		data['links'] = {};

		for (var i = 0; i < switches.length; i++)
		{
			var link = $("#" + prefix + "_link_" + switches[i]);
			if (!link.length)
			{
				alert("Can't find div for prefix " + prefix + " and switch " + switches[i]);
				return;
			}

			var margin = "3px";
			if (i < margins.length)
				margin = margins[i];

			var div = $("#" + prefix + "_div_" + switches[i]);
			div.css("margin-top", margin);

			data['divs'][switches[i]] = div;
			data['links'][switches[i]] = link;
		}

		for (i = 0; i < switches.length; i++)
		{
			(function(instance, data, state)
			{
				data['links'][state].bind("click", function()
				{
					if (!data['handler'].call(instance, state))
						return false;

					instance.change(data, state);
					return false;
				});
			})(this, data, switches[i]);
		}

		this.change(data, data['default']);
		this.options[prefix] = data;
	}
}

function option_links_change(data, state)
{
	data['state'] = state;

	var divs = data['divs'];
	var links = data['links'];

	var switches = data['switches'];
	for (var i = 0; i < switches.length; i++)
	{
		var id = switches[i];

		var div = divs[id];
		var link = links[id];

		if (id == state)
		{
			div.show();
			link.addClass("option_link_selected");
		}
		else
		{
			div.hide();
			link.removeClass("option_link_selected");
		}
	}
}

function option_links_get(prefix)
{
	if (typeof this.options[prefix] == "undefined")
		return null;

	return this.options[prefix]['state'];
}

function option_links_set(prefix, state)
{
	if (typeof this.options[prefix] == "undefined")
		return;

	this.change(this.options[prefix], state);
}