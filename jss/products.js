/**
 * @Version 1.0.2
 */

function products_init()
{
	$(".buy_link").bind("click", function()
	{
		var element = $(this);
		var item = element.closest(".item");

		element.hide();
		item.find(".basket_in_link").show();

		var id = item.find(".id").text();

		basket_add(id);
		return false;
	});

	image_preview_init();
}

function image_preview_init()
{
	var x_offset = 10;
	var y_offset = 30;

	$("a.preview").hover(function(e)
	{
		var element = $(this);
		var pref_div = element.prev("div.hide");
		this.title_old = this.title;
		this.title = "";
		var c = (this.title_old != "") ? "<br/>" + this.title_old : "";
		$("body").append("<p id='preview'><img src='" + pref_div.text() + "' alt='" + this.title_old +"' />" + c + "</p>");
		$("#preview")
			.css("top",(e.pageY - x_offset) + "px")
			.css("left",(e.pageX + y_offset) + "px")
			.fadeIn("fast");
	},function()
	{
		this.title = this.title_old;
		$("#preview").remove();
	});

	$("a.preview").mousemove(function(e)
	{
		$("#preview")
			.css("top",(e.pageY - x_offset) + "px")
			.css("left",(e.pageX + y_offset) + "px");
	});
}

$(document).ready(products_init);