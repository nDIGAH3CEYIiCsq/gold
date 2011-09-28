/**
 * Product
 * @Version 1.0.2
 */
Loader.scripts(["inputs"]);

function buy_link_init()
{
	$(".buy_link").bind("click", function()
	{
		var element = $(this);

		element.hide();
		element.next().show();

		var id = element.next().next().text();
		var sizes = $("#sizes_list input");
		sizes = Inputs.checkbox_get_values(sizes);

		basket_add(id, sizes);

		return false;
	});
}

$(document).ready(buy_link_init);