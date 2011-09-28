/**
 * Admin
 * @version 1.0.5
 */
function admin_init(states)
{
	var links = $(".admin_link");

	for (var i = links.length - 1; i >= 0; i--)
	{
		var element = links.eq(i);

		var pos = element.position();
		element.css({'position': "absolute", 'top': pos.top, 'left': pos.left});
	}

	links.hover(
		function()
		{
			if (this.dragging)
				return;
			$(this).css("background", "#d8ebfd");
		},
		function()
		{
			$(this).css("background", "none");
		}
	);

	admin_load_state(states);

	links.draggable({
		containment: ".content_zone",
		scroll: false,
		zIndex: 2700,
		start: function()
		{
			var element = $(this);
			this.orig_pos = element.position();
			this.dragging = true;

			element.css({'cursor': "default", 'background': "none"});
		},
		stop: function()
		{
			var element = $(this);
			var pos = element.position();
			this.dragging = false;

			var top = Math.round((pos.top - 66) / 76) * 76 + 66;
			var left = Math.round((pos.left - 10) / 76) * 76 + 10;

			if (!admin_can_drop(element, top, left))
			{
				top = this.orig_pos.top;
				left = this.orig_pos.left;
			}

			element.css({'top': top, 'left': left, 'cursor': "pointer"});

			admin_save_state(element);
		}
	});

	links.bind("click", function()
	{
		var a = $(this).find("a");
		if (!a.length)
			return;

		location.href = a.attr("href");
	});
}

function admin_can_drop(element, top, left)
{
	if (top < 0 || left < 0)
		return false;

	var element_id = element.attr("id");

	var links = $(".admin_link");
	for (var i = 0; i < links.length; i++)
	{
		var link = links.eq(i);
		if (link.attr("id") == element_id)
			continue;

		var pos = link.position();
		if (pos.top == top && pos.left == left)
			return false;
	}

	return true;
}

function admin_load_state(states)
{
	for (var link_id in states)
	{
		var link = $("#" + link_id);
		if (!link.length)
			continue;

		var pos = states[link_id];
		link.css({'top': pos[0], 'left': pos[1]});
	}
}

function admin_save_state(link)
{
	var pos = link.position();

	content_get("save_state", {'element': link.attr("id"), 'top': pos.top, 'left': pos.left});
}