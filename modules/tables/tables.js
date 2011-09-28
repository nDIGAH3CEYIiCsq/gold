/**
 * Tables
 * @version 1.0.2
 */
Loader.styles(["tables"]);

window.Tables = {
	'info': function(data)
	{
		var update = $("#update");

		update.html(data);
		update.show();
	}
};