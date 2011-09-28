/**
 * Questions
 * @uses Ajax
 * @uses SortedTable
 * @version 1.0.0
 */
Loader.styles(["jquery-datatables"]);
Loader.scripts(["ajax", "jquery-form", "sorted_table", "context_menu", "tables", "validator"], "managerreport_init");

var questions_table

function questions_init()
{
	questions_table = new SortedTable("questions",
	{
		'columns':
		[
			{sClass: "center"},
			{sClass: "left"},
			{sClass: "left"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center"},
			{
				"fnRender": function (oObj)
				{
					if (oObj.aData[2] != "")
						return "";
					return "<a href='?module=questions&action=answer&id=" + oObj.aData[8] + "'>Ответить</a>";
				},
				"aTargets": [8],
				sClass: "center",
				"bSortable": false
			},
			{
				"fnRender": function (oObj)
				{
					return "<a href='?module=questions&action=open&id=" + oObj.aData[9] + "'>Посмотреть</a>";
				},
				"aTargets": [9],
				sClass: "center lastchild",
				"bSortable": false
			},
			{sClass: "center lastchild"}

		],
		'options':
		{
			sAjaxSource: content_url({'action': "get_data"})
		},
		"aaSorting": [[ 0, "desc" ]]
	});
}

$(questions_init);
