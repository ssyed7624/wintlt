$(document).ready(function() {
	$.ajax({
        type:'POST',
        dataType:'json',
        url: baseUrl+"api/pgResponse/"+$('#gatewayName').val()+"/"+$('#pgTxnId').val(),
        data : JSON.parse($('#postData').val()),
        //timeout: limits.passenger_submit_timout, // sets timeout to 6 mins
        success:function(data){
            window.close();
        	//window.location.href =  data.returnUrl;
        }
    });
});