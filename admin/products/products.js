/**
 * Products
 * @uses jQuery Form
 * @uses jQuery Dynatree
 * @uses Categories
 * @uses Sorted_table
 * @uses Option_Links
 * @uses jQuery Form
 * @uses ContextMenu
 * @uses Inputs
 * @uses Arrays
 * @version 1.0.3
 */
Loader.styles(["jquery-dynatree"]);
Loader.scripts(["validator"]);
Loader.scripts(["jquery-form", "option_links", "inputs", "arrays"], "products_init");

var options;

function products_init()
{
	options = new OptionLinks(
	{
		'showing':
		{
			'switches': ["yes", "no"],
			'default': "yes",
			'handler': function(state)
			{
				$("#showing").val(state == "no" ? "0": "1");
				return true;
			}
		},
		'wedding':
		{
			'switches': ["yes", "no"],
			'default': "no",
			'handler': function(state)
			{
				$("#wedding").val(state == "no" ? "0": "1");
				return true;
			}
		}
	});

	$("#parent_id").bind("change", function()
	{
		var xml = content_get("get_children", {'parent_id': $("#parent_id").val()});
		$("#child_id").html(xml.text());
	})
	$("#parent_id").change();

	$("#clear_log_button").bind("click", function()
	{
		content_get("clear_log");
	});
}

$(products_init);