/**
 * Prototype
 * @version 1.0.1
 */
function prototype_extend(subClass, baseClass)
{
	function inheritance() {}
	inheritance.prototype = baseClass.prototype;
	subClass.prototype = new inheritance();
	subClass.prototype.constructor = subClass;
	subClass.parent = baseClass.prototype;
}