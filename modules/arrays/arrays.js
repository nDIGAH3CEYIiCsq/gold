/**
 * Arrays
 * @version 1.0.3
 */
window.Arrays = {
	'make_html': function(array)
	{
		var result = new Array();
		for (var i = 0; i < array.length; i++)
			result[i] = array.eq(i).html();

		return result;
	},
	'make_list': function(array)
	{
		var list = "";
		for (var i = 0; i < array.length; i++)
		{
			if (list != "")
				list += "<br />";
			list += array[i];
		}
		return list;
	},
	'has': function($value, $values)
	{
		for (var $id in $values)
		{
			if ($values[$id] == $value)
				return true;
		}

		return false;
	}
};