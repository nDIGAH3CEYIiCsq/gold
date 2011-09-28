/*
 * Личный кабинет
 * @version 1.0.0
 */

function private_init()
{
	var sex = $("#bind_sex").text();
	if (sex != "")
		$("input[name='sex'][value='" + sex + "']").attr('checked', true);

	var month = $("#bind_month").text();
	if (month != "")
		$("#month_birth").find("option[value='" + month  + "']").attr("selected", "selected");
};

$(document).ready(private_init);