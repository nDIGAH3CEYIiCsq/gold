/*
 *ArchiveOrder
* @uses Sorted_table
* @uses Inputs
* @version 1.0.1
**/
Loader.scripts(["sorted_table", "inputs"], "archive_order_init");

function archive_order_init()
{
	products_table = new SortedTable("products",
	{
		'columns':
		[
			null,
			{sClass: "center"},
			{sClass: "center"},
			{sClass: "center lastchild"}
		],
		'offset': 0,
		'options':
		{
			'bPaginate': false,
			'bFilter': false,
			'bLengthChange': false,
			'bInfo': false,
			'bServerSide': false,
			'bProcessing': false
		}

	});

	$("#products_table").ready(order_recalc_price);
	$("#delete_btn").bind("click", order_delete);
};

function order_delete()
{
	var order_id = $("#order_id").val();
	Ajax.post("?module=orders&action=delete", {'ids': [order_id]});
	window.close();
}


function order_recalc_price()
{
	var prices = $(".price");
	var result = 0;
	for (var i = 0; i < prices.length; i++)
	{
		var price = parseFloat(prices.eq(i).text());
		result += price;
	}
	$("#price").text(result);
}

archive_order_init();

