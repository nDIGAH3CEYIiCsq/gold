/**
 * Categories
 * @uses jQuery dynatree
 * @uses Ajax
 * @version 1.0.2
 */
Loader.scripts(["jquery-dynatree", "ajax"]);

var Categories = function(options)
{
	// Public
	Categories.prototype.init		= categories_init;
	Categories.prototype.disable		= categories_disable;
	Categories.prototype.get		= categories_get;
	Categories.prototype.get_selected	= categories_get_selected

	// Private
	Categories.prototype.load		= categories_load;
	Categories.prototype.get_key		= categories_get_key;
	Categories.prototype.get_node		= categories_get_node;
	Categories.prototype.gen_tree		= categories_gen_tree;
	Categories.prototype.children		= categories_children;

	this.init(options);
	this.load(this.get_node(0), options['active']);
}

function categories_init(options)
{
	this.options	= options;
	this.tree	= null;

	if (typeof this.options['folders'] == "undefined")
		this.options['folders'] = true;

	if (typeof this.options['root'] == "undefined")
		this.options['root'] = true;

	if (typeof this.options['all'] == "undefined")
		this.options['all'] = 0;
	else
		this.options['all'] = 1;

	if (typeof this.options['active'] == "undefined")
		this.options['active'] = 0;

	if (typeof this.options['checkbox'] == "undefined")
		this.options['checkbox'] = false;

	if (typeof this.options['min_level'] == "undefined")
		this.options['min_level']  =  1;

	this.tree = $(this.options['owner']);

	this.tree.dynatree(
	{
		title: this.options['title'],
		idPrefix: "",
		rootVisible: this.options['root'],
		minExpandLevel: this.options['min_level'],
		keyboard: true,
		clickFolderMode: 1,
		selectMode: 3,
		checkbox: this.options['checkbox'],
		onActivate: function (instance)
		{
			return function (node)
			{
				var key = instance.get_key(node);

				if (!instance.options['callback'])
					return;

				var callback = instance.options['callback'];

				callback(key);
			}
		}(this),
		onLazyRead: function (instance)
		{
			return function (node)
			{
				instance.load(node, 0);
			}
		}(this),
		onPostInit: function(isReloading, isError)
		{
			logMsg("onPostInit(%o, %o)", isReloading, isError);
			this.reactivate();
		},
		onDblClick: function(dtnode, event)
		{
			dtnode.toggleExpand();
		}

	});
}

function categories_disable(disable)
{
	this.tree.attr("disabled", disable);
}

function categories_get()
{
	var node = this.tree.dynatree("getActiveNode");

	return this.get_key(node);
}

function categories_gen_tree(root, prefix)
{
	var categories = root.children("category");
	if (!categories.length)
		return [];

	var result = new Array();
	for (var i = 0; i < categories.length; i++)
	{
		var category = categories.eq(i);
		var name = category.attr("name");

		name = prefix + "/" + name;
		result[result.length] = name;

		var tree = this.gen_tree(category, name);
		$.merge(result, tree);
	}

	return result;
}

function categories_load(node, active)
{
	var id = this.get_key(node);

	var xml = Ajax.post(this.options['url'], {'id': id, 'active': active, 'all': this.options['all']});
	if (!Ajax.check_error(xml))
		return;

	node.removeChildren();
	this.children(node, xml);
}

function categories_children(parent, xml)
{
	var elements = xml.children("category");
	for (var i = 0; i < elements.length; i++)
	{
		var element	= elements.eq(i);

		var id		= element.attr("id");
		var name	= element.attr("name");
		var expand	= element.attr("expand");
		var children	= element.attr("children");

		var data = {'title': name, 'key': id, 'expand': expand};

		if (children == 1)
		{
			data['isLazy'] = true;
			data['isFolder'] = this.options['folders'];
		}

		var child = parent.addChild(data);
		this.children(child, element);
	}
}

function categories_get_key(node)
{
	if (node.data.key == "root")
		return 0;

	return node.data.key;
}

function categories_get_node(key)
{
	if (key == 0)
		return this.tree.dynatree("getRoot");

	return this.tree.dynatree("getTree").getNodeByKey(key);
}

function categories_get_selected()
{
	return this.tree.dynatree("getSelectedNodes")
}