/**
 * Loader
 * @version 1.0.3
 */
window.Loader = {
	'loaded': {},

	'replace': function(success)
	{
		return function(success)
		{
			return function()
			{
				var args = arguments;

				$(document).ready(function()
				{
					success.apply(window, args);
				});
			};
		}(success);
	},
	'scripts': function(scripts, success)
	{
		var head = $("head");

		for (var i = 0; i < scripts.length; i++)
		{
			var url = this.url(scripts[i], "js");
			if (this.loaded[url])
				continue;

			this.loaded[url] = true;

			head.append("<script type='text/javascript' src='" + url + "'></script>");
		}

		if (typeof success !== "undefined")
			window[success] = this.replace(window[success]);
	},
	'styles': function(styles)
	{
		var head = $("head");

		for (var i = 0; i < styles.length; i++)
		{
			var url = this.url(styles[i], "css");

			head.append("<link type='text/css' href='" + url + "' rel='stylesheet' />");
		}
	},
	'url': function(name, extension)
	{
		return "/modules/" + name + "/" + name + "." + extension;
	}
};