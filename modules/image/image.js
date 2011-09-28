/**
 * Image
 * @version 1.0.1
 */
function image_onload(url, callback)
{
	var img = new Image();
	img.src = url;

	if (img.complete)
	{
		callback();
		return;
	}

	$(img).bind("load", function()
	{
		callback();
	});
}