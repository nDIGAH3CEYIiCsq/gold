/**
 * Ajax
 * @uses Validator
 * @version 1.0.7
 */
Loader.scripts(["validator"]);

window.Ajax = {
	'post': function(url, data, data_type)
	{
		if (typeof data_type == "undefined")
			data_type = "xml";

		url = Ajax.nocache(url);

		var answer = $.ajax({'url': url, 'type': "POST", 'async': false, 'data': data, 'dataType': data_type});

		if (data_type != "xml")
			return answer.responseText;

		try
		{
			if (!answer.responseXML)
				throw "";

			var result = $(answer.responseXML).children("answer");
			if (!result.length)
				throw "";

			return result;
		}
		catch(e)
		{}

		try
		{
			var errors = "<errors><![CDATA[" + unescape(answer.responseText) + "]]></errors>";

			var xml;
			if (typeof DOMParser != "undefined")
			{
				var doc = new DOMParser();
				xml = doc.parseFromString(errors, "text/xml");
			}
			else
			{
				xml = new ActiveXObject("Microsoft.XMLDOM");
				xml.async = false;
				xml.loadXML(errors);
			}

			return $(xml);
		}
		catch (e)
		{
			alert(answer.responseText);
		}
		return false;
	},
	'nocache': function(url)
	{
		if (location.href.match(new RegExp("[&?]nocache(#|&|=|$)")))
			url += "&nocache";

		return url;
	},
	'check_error': function(xml, map)
	{
		xml = $(xml);

		var errors = xml.find("errors").text();
		if (errors == "")
			return true;

		Validator.reset();

		if (typeof map !== "undefined")
			Validator.map(map);

		$("body").append(errors);
		return false;
	},
	'xml_load': function(element, url, data)
	{
		var xml = Ajax.send_post(url, data);

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