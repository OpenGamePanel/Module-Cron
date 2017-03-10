$(document).ready(function(){
    $("select[name='homeid_ip_port']").change(function(e){
		checkSteamSupportAutoUpdate($(this));
	});
	
	checkSteamSupportAutoUpdate($("select[name='homeid_ip_port']").first());
});

function checkSteamSupportAutoUpdate(elem){
	var curOpt = $("option:selected", $(elem));
	if(curOpt.attr('steam')){
		$("option[value='steam_auto_update']", $("select[name='action']", $(elem).parent().prev())).removeAttr('disabled');
	}else{
		$("option[value='steam_auto_update']", $("select[name='action']", $(elem).parent().prev())).attr('disabled','disabled');
		if($("option[value='steam_auto_update']", $("select[name='action']", $(elem).parent().prev())).is(':selected')){
			$("select[name='action'] option:enabled:first", $(elem).parent().prev()).prop('selected', 'selected').change();
		}
	}
}
