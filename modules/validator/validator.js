/**
 * Validator
 * @uses Inputs
 * @uses Trim
 * @version 1.0.6
 */
Loader.styles(["validator"]);
Loader.scripts(["inputs", "trim"]);

window.Validator = {
	'has_errors': false,
	'errors_map': {},

	'map': function(map)
	{
		Validator.errors_map = map;
	},
	'errors': function()
	{
		return Validator.has_errors;
	},
	'captcha': function()
	{
		$(".captcha_image").each(function()
		{
			var captcha = $(this);

			var src = captcha.attr("src");
			var pos = src.indexOf("?");
			if (pos !== -1)
				src = src.substring(0, pos);

			captcha.attr("src", src + "?" + Math.random());
		});
	},
	'get': function(object, all)
	{
		object = Validator.find(object);
		if (object === null)
			throw "";

		var value = "";

		var tag_name = object[0].tagName;
		switch (tag_name)
		{
			case "INPUT":
				value = trim(object.val());
				break;
			case "TEXTAREA":
				value = object.val();
				break;
			case "SELECT":
				if (all)
				{
					value = Inputs.select_get_values(object);
					if (!value.length)
						value = "";
				}
				else
				{
					value = object.val();
					if (value === null)
						value = "";

					if (typeof value == "object")
						break;

					value = trim(value);
				}
				break;
		}

		if (value !== "")
			return value;

		Validator.string(object);
		return null;
	},
	'show': function(object, text, clear)
	{
		Validator.has_errors = true;

		object = Validator.find(object);
		if (object === null)
		{
			alert(text);
			return;
		}

		if (clear)
			Validator.reset();

		object.addClass("error_selected");

		var errors = Validator.container(object);
		if (!errors)
			return;

		if (errors.text() != "")
			text = "<br />" + text;

		errors.append(text);
		errors.show();
	},
	'name': function(object)
	{
		var name = object.attr("title");
		if (name !== "")
			return name;

		var id = object.attr("id");
		if (id !== "")
		{
			var label = $("label[for=" + id + "]");
			if (label.length == 1)
				return trim(label.text(), "\\s:");
		}

		return object.attr("name");
	},
	'update': function(form, text)
	{
		var update = form.find(".update");

		if (typeof text != "undefined")
			update.text(text).show();
		else
			update.empty().hide();

	},
	'reset': function(arr, form)
	{
		$(".error_selected").removeClass("error_selected");
		$(".errors").empty().hide();

		if (typeof form != "undefined")
			Validator.update(form);

		Validator.has_errors = false;
	},
	'find': function(object)
	{
		if (typeof object != "string")
			return object;

		if (typeof Validator.errors_map[object] != "undefined")
			object = Validator.errors_map[object];

		if (typeof object != "string")
			return object;

		return $("#" + object);
	},
	'form': function(inputs, form)
	{
		Validator.reset();

		for (var name in inputs)
		{
			var input = inputs[name];
			if (input['value'] !== "")
				continue;

			var element = form.find("[name='" + input['name'] + "']");
			if (!element.length)
				continue;

			Validator.string(element);
		}

		return !(Validator.errors());
	},
	'container': function(object)
	{
		while (object.length)
		{
			var errors = object.find(".errors");
			if (errors.length)
				return errors;

			object = object.parent();
		}

		alert("Errors: Can't find '.errors' container");
		return null;
	},
	'string': function(object)
	{
		object = Validator.find(object);
		if (!object.length)
			throw "";

		var name = Validator.name(object);

		Validator.show(object, "Поле <b>" + name + "</b> обязательно для заполнения");
	},
	'object': function(param)
	{
		for (var key in param)
			Validator.show(key, "Поле <b>" + param[key] + "</b> обязательно для заполнения");
	}
};