/**
 * Inputs
 * @version 1.0.3
 */
window.Inputs = {
	'select_get_values': function(element)
	{
		var result = new Array();

		var options = element.find("option:[value!='']");

		for (var i = 0; i < options.length; i ++)
			result[i] = options.eq(i).val();

		return result;
	},
	'checkbox_get_values': function(checkboxes)
	{
		var result = new Array();
		for (var i = 0; i < checkboxes.length; i ++)
		{
			var checkbox = checkboxes.eq(i);
			if (!checkbox.attr("checked"))
				continue;

			result[result.length] = checkbox.attr("value");
		}

		return result;
	},
	'checkbox_toogle': function(owner)
	{
		var element = $(owner.target);

		var checkboxes;
		var table = element.closest("table");
		checkboxes = table.find("input:enabled[type=checkbox]");

		checkboxes.attr("checked", element.attr("checked"));

		window.event.cancelBubble = true;
	},
	'fill_values': function(names, xml, prefix)
	{
		for (var i = 0; i < names.length; i++)
		{
			var name = names[i];
			if (typeof prefix != "undefined")
				name = prefix + name;

			var value = xml.attr(name);
			if (typeof value == "undefined")
				continue;

			var element = $("#" + name);
			var type = element[0].type;

			switch (type)
			{
				case "text":
					element.val(value);
					break;
				case "checkbox":
					element.attr("checked", value == "1");
					break;
			}
		}
	},
	'fill_options': function(element, xml)
	{
		var options = xml.find("element");
		for (var i = 0; i < options.length; i++)
		{
			var option = options.eq(i);

			var name = option.attr("name");
			var value = option.attr("value");

			element.append("<option value='" + value + "'>" + name + "</option>");
		}

		if (options.length != 0)
			element.removeAttr("disabled");
	}
};