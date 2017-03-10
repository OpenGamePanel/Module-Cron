$(document).ready(function(){
    $("select[name='homeid_ip_port']").change(function(e){
		checkSteamSupportAutoUpdate();
	});
	checkSteamSupportAutoUpdate();
});

function checkSteamSupportAutoUpdate(){
	var curOpt = $("select[name='homeid_ip_port'] option:selected");
	if(curOpt.attr('steam')){
		$("option[value='steam_auto_update']", $("select[name='action']")).removeAttr('disabled');
	}else{
		$("option[value='steam_auto_update']", $("select[name='action']")).attr('disabled','disabled');
	}
}
