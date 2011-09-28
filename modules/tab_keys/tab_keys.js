/**
 * TabKeys
 * @version 1.0.3
 */
window.TabKeys = {
	'init': function(collection)
	{
		if (typeof document.selection != "undefined")
		{
			collection.live("keydown", this.down);
			collection.live("keypress", this.up);
		}
		else
			collection.live("keypress", this.press);
	},
	'remove': function(text)
	{
		return text.replace(new RegExp("(^|\\n)\\t", "g"), "$1");
	},
	'insert': function(text)
	{
		return text.replace(new RegExp("(^|\\n)([\\t\\S])", "g"), "$1\t$2");
	},
	'up': function(event)
	{
		if (event.keyCode != 9)
			return true;
		return false;
	},
	'down': function(event)
	{
		if (event.keyCode != 9)
			return true;

		var range = document.selection.createRange();
		if (!range.text.length)
		{
			range.text = "\t";
			return false;
		}

		if (event.shiftKey)
			range.text = TabKeys.remove(range.text);
		else
			range.text = TabKeys.insert(range.text);

		return false;
	},
	'press': function(event)
	{
		if (event.keyCode != 9)
			return true;

		var text_before = "", text_selected = "", text_after = "";

		var scroll_top = this.scrollTop;
		var selection_start = this.selectionStart;
		var has_selection = false;

		if (this.selectionStart != 0)
			text_before = this.value.substring(0, this.selectionStart);
		if (this.selectionEnd != this.value.length)
			text_after = this.value.substring(this.selectionEnd, this.value.length);

		if (this.selectionStart != this.selectionEnd)
		{
			text_selected = this.value.substring(this.selectionStart, this.selectionEnd);

			if (event.shiftKey)
				text_selected = TabKeys.remove(text_selected);
			else
				text_selected = TabKeys.insert(text_selected);

			has_selection = true;
		}
		else
			text_selected = "\t";

		this.value = text_before + text_selected + text_after;
		this.focus();

		if (has_selection)
		{
			this.selectionStart = selection_start;
			this.selectionEnd = selection_start + text_selected.length;
		}
		else
		{
			this.selectionStart = selection_start + 1;
			this.selectionEnd = this.selectionStart;
		}

		this.scrollTop = scroll_top;
		return false;
	}
};