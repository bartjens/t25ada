$(document).ready(function () {
	$('#doseerCode').keyup(function () {
		checkDoseerCode();
	});

});


var checkDoseerCode = function () {

	var codeLine = $('#doseerCode').val().toUpperCase();

	resultaatveld = 't25result';

	var jqxhr =
	$.ajax({
		url: 'parse.php',
		type : "POST",
		data: {t25Code : codeLine}
	})

	.done (function(response) {
				// Zet het resultaatveld visible (Want die kan wel eens hidden zijn
					//document.getElementById(resultaatveld).style.visibility='visible';
		$('#' + resultaatveld).css('visibility','visible');
		$('#' + resultaatveld).show();
		$('#' + resultaatveld).stop(true,true);
		$('#' + resultaatveld).html(response);

	})

	.fail(function(jqXHR, textStatus) {
	  console.log( "Request failed: " , textStatus );
	})
}


