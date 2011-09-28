/**
 * Client
 * @version 1.0.1
 */

function client_init()
{
	$("#cancel_btn").bind("click", function()
	{
		window.close();

	});
	clients_bind_data();
}

function clients_bind_data()
{
	var sex = $("#bind_sex").text();
	if (sex != "")
		$("input[name='sex'][value='" + sex + "']").attr('checked', true);

	var month = $("#bind_month").text();
	if (month != "")
		$("#month_birth").find("option[value='" + month  + "']").attr("selected", "selected");
}

$(document).ready(client_init);