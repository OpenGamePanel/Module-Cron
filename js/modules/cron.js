$(document).ready(function(){
    $("select[name='homeid_ip_port']").change(function(e){
		checkSteamSupportAutoUpdate($(this));
	});
	
	checkSteamSupportAutoUpdate($("select[name='homeid_ip_port']").first());
});

function checkSteamSupportAutoUpdate(elem){
	var curOpt = $("option:selected", $(elem));
	if(curOpt.attr('steam')){
		$("option[value='steam_auto_update']", $("select[name='action']")).removeAttr('disabled');
	}else{
		$("option[value='steam_auto_update']", $("select[name='action']")).attr('disabled','disabled');
	}
}
