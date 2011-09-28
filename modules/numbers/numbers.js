/**
 * Numbers
 * @version 1.0.1
 */

function is_int_positive(number, max)
{
	if (isNaN(number) || number < 0)
		return false;

	if (typeof max != "undefined")
		return number < max;
	
	return true;
}