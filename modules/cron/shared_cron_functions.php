<?php
/*
 *
 * OGP - Open Game Panel
 * Copyright (C) 2008 - 2018 The OGP Development Team
 *
 * http://www.opengamepanel.org/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */
function reloadJobs($server_homes, $remote_servers, $getAllJobs = true)
{
	global $db;
	$remote_servers_offline = array();
	$jobsArray = array();
	foreach( $remote_servers as $remote_server )
	{
		$remote = new OGPRemoteLibrary($remote_server['agent_ip'], $remote_server['agent_port'], $remote_server['encryption_key'], $remote_server['timeout']);
		$rhost_id = $remote_server['remote_server_id'];
		if($remote->status_chk() != 1)
		{
			$remote_servers_offline[$rhost_id] = $remote_server;
			continue;
		}
		else
		{
			$jobs = $remote->scheduler_list_tasks();
			if($jobs != -1)
			{
				foreach($jobs as $jobId => $job)
				{
					list($minute,$hour,$dayOfTheMonth,$month,$dayOfTheWeek,$command) = explode(" ", $job, 6);
					if(preg_match('/'.preg_quote('wget -qO- "','/').'([^"]+)'.preg_quote('" --no-check-certificate > /dev/null 2>&1','/').'/', $command))
					{
						list($wget,$wget_args,$url,$wget_nocert,$gt,$devnull,$err2out) =  explode(" ", $command, 7);
						//echo "$wget,$wget_args,$url,$wget_nocert,$gt,$devnull,$err2out<br>";
						parse_str(parse_url(trim($url,'"'), PHP_URL_QUERY), $url_query);
						
						if(!isset($url_query['ip']) or !isset($url_query['port']))
							continue;
						$home_info = $db->getGameHomeByIP($url_query['ip'], $url_query['port']);
						if(!$getAllJobs && !hasAccess($home_info))
							continue;
						
						$action = key($url_query);
						if($action == "gamemanager/update"){
							$action = "steam_auto_update";
						}else if($action == "gamemanager/stop"){
							$action = "stop";
						}else if($action == "gamemanager/start"){
							$action = "start";
						}else if($action == "gamemanager/restart"){
							$action = "restart";
						}
						
						$jobsArray[$rhost_id][$jobId] = array( 'job' => $job, 
															   'minute' => $minute, 
															   'hour' => $hour, 
															   'dayOfTheMonth' => $dayOfTheMonth, 
															   'month' => $month, 
															   'dayOfTheWeek' => $dayOfTheWeek,
															   'command' => $command,
															   'action' => $action,
															   'home_id' => $home_info['home_id'],
															   'ip' => $home_info['ip'],
															   'port' => $home_info['port'],
															   'mod_key' => $url_query['mod_key']);
					}
					else
					{	
						if(!$getAllJobs && !$db->isAdmin($_SESSION['user_id'])){
							continue;
						}			
						
						$jobsArray[$rhost_id][$jobId] = array( 'job' => $job, 
															   'minute' => $minute, 
															   'hour' => $hour, 
															   'dayOfTheMonth' => $dayOfTheMonth, 
															   'month' => $month, 
															   'dayOfTheWeek' => $dayOfTheWeek, 
															   'command' => $command);
					}
				}
			}
		}
	}
	return array($jobsArray, $remote_servers_offline);
}

function updateCronJobPasswords($db, $remote, $changedHomeId){
	$homes = $db->getIpPorts();

	foreach( $homes as $home )
	{
		$id = $home['home_id']."_".$home['ip']."_".$home['port'];
		$server_homes[$id] = $home;
		$server_id = $home['remote_server_id'];
		$remote_servers[$server_id] = array("remote_server_id" => $home['remote_server_id'],
											"remote_server_name" => $home['remote_server_name'],
											"ogp_user" => $home['ogp_user'],
											"agent_ip" => $home['agent_ip'],
											"agent_port" => $home['agent_port'],
											"ftp_port" => $home['ftp_port'],
											"encryption_key" => $home['encryption_key'],
											"timeout" => $home['timeout'],
											"use_nat" => $home['use_nat'],
											"ftp_ip" => $home['ftp_ip']);
	}

	list($jobsArray, $remote_servers_offline) = reloadJobs($server_homes, $remote_servers);

	$homes = customShift($homes, "home_id", $changedHomeId);
	$homeIdStr = "homeid=";
	$actionStr = "action=";
	$cPassStr = "controlpass=";

	if(count($homes) > 0){
		$home = $homes[0];
		if($home["home_id"] == $changedHomeId){ 
			$control_password = $home['control_password'];
			
			foreach( $jobsArray as $remote_server_id => $jobs )
			{
				if($home['remote_server_id'] == $remote_server_id){
					foreach($jobs as $jobId => $job)
					{
						$command = $job['command'];
						$homeId = getURLParam($homeIdStr, $command);
						$action = getURLParam($actionStr, $command);
						if($homeId !== false && $action !== false){
							if($homeId == $changedHomeId){
								$curPass = getURLParam($cPassStr, $command);
								if(stripos($curPass, '" --no-check-certificate') !== false){
									$curPass = substr($curPass, 0, stripos($curPass, '" --no-check-certificate'));
								}else if(strrpos($curPass, '"') !== false){
									$curPass = substr($curPass, 0, strrpos($curPass, '"'));
								}								
								if($curPass != $control_password){
									$command = str_replace($cPassStr . $curPass, $cPassStr . $control_password, $command);
									$minute = $job['minute'];
									$hour = $job['hour'];
									$dayOfTheMonth = $job['dayOfTheMonth'];
									$month = $job['month'];
									$dayOfTheWeek = $job['dayOfTheWeek'];
									
									$job = $minute." ".
										$hour ." ".
										$dayOfTheMonth." ".
										$month." ".
										$dayOfTheWeek." ".
										$command;
										
									$remote->scheduler_edit_task($jobId, $job);						
								}
							}
						}
					}
				}
			}
		}
	}
}

function get_action_selector($action = false, $server_homes = false, $homeid_ip_port = false) {
	$server_actions = array('restart','stop','start');
	if($server_homes and $homeid_ip_port)
	{
		$server_xml = read_server_config(SERVER_CONFIG_LOCATION."/".$server_homes[$homeid_ip_port]['home_cfg_file']);
		if( $server_xml->installer == "steamcmd" )
			$server_actions[] = 'steam_auto_update';
	}
	$select_action = '<select name="action" style="width: 100%;">';
	foreach($server_actions as $server_action)
	{
		$selected = ($action and $action == $server_action) ? 'selected="selected"' : '';
		$select_action .= '<option value="'.$server_action.'" '.$selected.'>'.get_lang($server_action).'</option>';
	}
	return $select_action .= '</select>';
}

function get_server_selector($server_homes, $homeid_ip_port = FALSE, $onchange = FALSE, $includeRemoteName = false) {
	$onchange_this_form_submit = $onchange ? 'onchange="this.form.submit();"' : '';
	$select_game = "<select style='text-overflow: ellipsis; width: 100%;' name='homeid_ip_port' $onchange_this_form_submit>\n";
	if($server_homes != FALSE)
	{
		foreach ( $server_homes as $server_home )
		{
			$selected = ($homeid_ip_port and ($homeid_ip_port == $server_home['home_id']."_".$server_home['ip']."_".$server_home['port'] || trim($homeid_ip_port) == trim($server_home['home_id']))) ? 'selected="selected"' : '';
			$select_game .= "<option value='". $server_home['home_id'] . "_" . $server_home['ip'] .
							"_" . $server_home['port'] . "' $selected " . $additionalMarkup . ">" . $server_home['home_name'] . 
							" - " . checkDisplayPublicIP($server_home['display_public_ip'],$server_home['ip'] != $server_home['agent_ip'] ? $server_home['ip'] : $server_home['agent_ip']) . ":" .$server_home['port'];
			if($includeRemoteName){
				$select_game .= " ( " . $server_home['remote_server_name'] . " )";
			}
			
			$select_game .= "</option>\n";
		}
	}
	return $select_game .= "</select>\n";
}

function get_remote_server_selector($r_servers, $remote_servers_offline, $remote_server_id = FALSE, $onchange = FALSE, $first_empty = FALSE ) {
	$onchange_this_form_submit = $onchange ? 'onchange="this.form.submit();"' : '';
	$select_rserver = "<select id='r_server_id' style='width: 100%;' name='r_server_id' $onchange_this_form_submit>\n";
	if($first_empty) $select_rserver .= '<option></option>';
	foreach ( $r_servers as $r_server )
	{
		$selected = ($remote_server_id and $remote_server_id == $r_server['remote_server_id']) ? 'selected="selected"' : '';
		$offline = isset($remote_servers_offline[$r_server['remote_server_id']]) ? ' (' . offline . ')' : '';
		$select_rserver .= "<option value='". $r_server['remote_server_id'] . "' $selected>" . $r_server['remote_server_name'] . "$offline</option>\n";
	}
	return $select_rserver .= "</select>\n";
}

function checkCronInput($min, $hour, $day, $month, $dayOfWeek) {
    $blacklist = '"#$%^&()+=[]\';{}|:<>?~';
    $returns = array();
    
    $args = func_get_args();
    
    foreach ($args as $k => $arg) {
        if (strlen($arg) == 0 || strpbrk($arg, $blacklist) || preg_match('/\\s/', $arg)) {
            $returns[$k] = false;
        }
    }
    
    return (empty($returns) ? true : false);
}

function hasAccess($home_info){
	global $db;
	return ($home_info and $db->isAdmin($_SESSION['user_id'])) ? true : ($home_info and $db->getUserGameHome($_SESSION['user_id'], $home_info['home_id']));
}

?>
