<script type="text/javascript" src="js/modules/cron.js"></script>
<?php
/*
 *
 * OGP - Open Game Panel
 * Copyright (C) 2008 - 2017 The OGP Development Team
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
error_reporting(E_ALL);
require_once('includes/lib_remote.php');
require_once('modules/gamemanager/home_handling_functions.php');
require_once('modules/config_games/server_config_parser.php');
require_once('modules/cron/shared_cron_functions.php');

function exec_ogp_module() 
{
	global $db, $view;
	$isAdmin = $db->isAdmin($_SESSION['user_id']);
	$boolShowedAdminLink = false;

	$homes = $db->getIpPortsForUser($_SESSION['user_id']);
	if(!$homes)
	{
		print_failure(get_lang('cron_no_servers_tied_to_account'));
		if($isAdmin){
			$boolShowedAdminLink = true;
			echo '<a href="home.php?m=cron&p=cron">' . get_lang('cron_admin_link_display_text') . '</a>';
		}
		return 0;
	}
	
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
	
	if( isset($_POST['addJob']) or isset($_POST['editJob']) )
	{
		if ( isset( $_POST['homeid_ip_port'] ) and isset($server_homes[$_POST['homeid_ip_port']]) )
		{
			$game_home = $server_homes[$_POST['homeid_ip_port']];
			$server_xml = read_server_config(SERVER_CONFIG_LOCATION."/".$game_home['home_cfg_file']);
			$remote = new OGPRemoteLibrary( $game_home['agent_ip'],
											$game_home['agent_port'],
											$game_home['encryption_key'],
											$game_home['timeout']);
			$home_id = $game_home['home_id'];
			$ip = $game_home['ip'];
			$port = $game_home['port'];
			$control_protocol = $server_xml->control_protocol;
			$control_password = $game_home['control_password'];
			$control_type = $server_xml->control_protocol_type;
			$home_path = $game_home['home_path'];
			$server_exe = $server_xml->server_exec_name;
			$run_dir = $server_xml->exe_location;
			$game_home['mods'][$game_home['mod_id']] = Array ("mod_cfg_id" => $game_home['mod_cfg_id'],
															  "max_players" => $game_home['max_players'],
															  "extra_params" => $game_home['extra_params'],
															  "cpu_affinity" => $game_home['cpu_affinity'],
															  "nice" => $game_home['nice'],
															  "precmd" => $game_home['precmd'],
															  "postcmd" => $game_home['postcmd'],
															  "home_cfg_id" => $game_home['home_cfg_id'],
															  "mod_key" => $game_home['mod_key'],
															  "mod_name" => $game_home['mod_name'],
															  "def_precmd" => $game_home['def_precmd'],
															  "def_postcmd" => $game_home['def_postcmd']);
			$startup_cmd = get_start_cmd($remote,$server_xml,$game_home,$game_home['mod_id'],$game_home['ip'],$game_home['port'], $db);
			$cpu = $game_home['cpu_affinity'];
			$nice = $game_home['nice'];
			
			$panelURL = getOGPSiteURL();
			if($panelURL === false){
				print_failure('Failed to retrieve panel URL.');
				return 0;
			}
			
			switch ($_POST['action']) {
				case "stop":
					$command = "wget -qO- \"" . $panelURL . "/ogp_api.php?action=stopServer&homeid=" . $home_id . "&controlpass=" . $control_password . "\" --no-check-certificate > /dev/null 2>&1";
					break;
				case "start":
					$command = "wget -qO- \"" . $panelURL . "/ogp_api.php?action=startServer&homeid=" . $home_id . "&controlpass=" . $control_password . "\" --no-check-certificate > /dev/null 2>&1";
					break;
				case "restart":
					$command = "wget -qO- \"" . $panelURL . "/ogp_api.php?action=restartServer&homeid=" . $home_id . "&controlpass=" . $control_password . "\" --no-check-certificate > /dev/null 2>&1";
					break;
				case "steam_auto_update":
					$command = "wget -qO- \"" . $panelURL . "/ogp_api.php?action=autoUpdateSteamHome&homeid=" . $home_id . "&controlpass=" . $control_password . "\" --no-check-certificate > /dev/null 2>&1";
					break;
			}

			if (!checkCronInput($_POST['minute'], $_POST['hour'], $_POST['dayOfTheMonth'], $_POST['month'], $_POST['dayOfTheWeek'])) {
				print_failure(get_lang('OGP_LANG_bad_inputs'));
				$view->refresh('?m=cron&p=user_cron');

				return;
			}

			$job = $_POST['minute']." ".
				   $_POST['hour']." ".
				   $_POST['dayOfTheMonth']." ".
				   $_POST['month']." ".
				   $_POST['dayOfTheWeek']." ".
				   $command;
			
			if( isset($_POST['editJob']) and isset($jobsArray[$_POST['r_server_id']][$_POST['job_id']]) ) 
				$remote->scheduler_edit_task($_POST['job_id'], $job);
			elseif( isset($_POST['addJob']) ) 
				$remote->scheduler_add_task($job);
			list($jobsArray, $remote_servers_offline) = reloadJobs($server_homes, $remote_servers);
		}
	}
	elseif( isset($_POST['removeJob']) and isset($remote_servers[$_POST['r_server_id']]) and isset($jobsArray[$_POST['r_server_id']][$_POST['job_id']]) )
	{	
		$remote = new OGPRemoteLibrary( $remote_servers[$_POST['r_server_id']]['agent_ip'],
										$remote_servers[$_POST['r_server_id']]['agent_port'],
										$remote_servers[$_POST['r_server_id']]['encryption_key'],
										$remote_servers[$_POST['r_server_id']]['timeout'] );
		$remote->scheduler_del_task($_POST['job_id']);
		list($jobsArray, $remote_servers_offline) = reloadJobs($server_homes, $remote_servers);
	}	

	echo "<h2>" . get_lang("schedule_new_job") . "</h2>";
	require_once("includes/refreshed.php");
	$refresh = new refreshed();
	$homeid_ip_port = isset($_POST['homeid_ip_port']) ? $_POST['homeid_ip_port'] : key($server_homes);
	$r_server_id = $server_homes[$homeid_ip_port]['remote_server_id'];
	$curtime = $refresh->add( "home.php?m=cron&p=thetime&r_server_id=$r_server_id&type=cleared" );
	echo "<pre class='log' ><table><tr><td>" . get_lang("now") . 
		"&nbsp;</td><td><form action='' method='POST' >" . get_server_selector($server_homes, $homeid_ip_port, TRUE) . 
		"</form></td></tr></table> <b style='font-size:1.4em;'>" . $refresh->getdiv($curtime) . "</b></pre>";
 ?>
<form method="POST" >	
<table class="center hundred">
	<tr>
		<th>
		<?php echo get_lang("minute"); ?>
		</th>
		<th>
		<?php echo get_lang("hour"); ?>
		</th>
		<th>
		<?php echo get_lang("day"); ?>
		</th>
		<th>
		<?php echo get_lang("month"); ?>
		</th>
		<th>
		<?php echo get_lang("day_of_the_week"); ?>
		</th>
		<th>
		<?php echo get_lang("action"); ?>
		</th>
		<th>
		<?php echo get_lang("user_games"); ?>
		</th>
	</tr>
	<tr>
		<td style="width: 35px;" >
			<input style="width: 30px;" type="text" name="minute" value="*" />
		</td>
		<td style="width: 35px;" >
			<input style="width: 30px;" type="text" name="hour" value="*" />
		</td>
		<td style="width: 35px;" >
			<input style="width: 30px;" type="text" name="dayOfTheMonth" value="*" />
		</td>
		<td style="width: 35px;" >
			<input style="width: 30px;" type="text" name="month" value="*" />
		</td>
		<td style="width: 35px;" >
			<input style="width: 30px;" type="text" name="dayOfTheWeek" value="*" />
		</td>
		<td>
			<?php echo get_action_selector();?>
		</td>
		<td>
			<?php echo get_server_selector($server_homes, $homeid_ip_port);?>
		</td>
		<td style="width: 132px;">
			<input style="" type="submit" name="addJob" value="<?php echo get_lang("add"); ?>" />
		</td>
	</tr>
</table>
</form>
<br>
<h2><?php echo get_lang("scheduled_jobs");?></h2>
<?php
	if ( !empty($jobsArray) )
	{
?>
<table class="center hundred">
	</tr>
	<tr>
		<th>
		<?php echo get_lang("minute"); ?>
		</th>
		<th>
		<?php echo get_lang("hour"); ?>
		</th>
		<th>
		<?php echo get_lang("day"); ?>
		</th>
		<th>
		<?php echo get_lang("month"); ?>
		</th>
		<th>
		<?php echo get_lang("day_of_the_week"); ?>
		</th>
		<th>
		<?php echo get_lang("action"); ?>
		</th>
		<th>
		<?php echo get_lang("user_games"); ?>
		</th>
	</tr>
<?php
		$user_jobs = "";
		foreach( $jobsArray as $remote_server_id => $jobs )
		{
			foreach($jobs as $jobId => $job)
			{				
				if(isset($job['action']))
				{
					if(array_key_exists('home_id', $job) && array_key_exists('ip', $job) && array_key_exists('port', $job) && hasValue($job['home_id']) && hasValue($job['ip']) && hasValue($job['port'])){
						$uniqueStr = $job['home_id']."_".$job['ip']."_".$job['port'];
					}else if(hasValue($job['home_id'])){
						$uniqueStr = $job['home_id'];
					}
					
					if(hasValue(@$uniqueStr)){
						$user_jobs .=  '<tr>
										<td style="width: 35px;" >
											<form method="POST" >
											<input style="width: 30px;" type="text" name="minute" value="'.$job['minute'].'" />
										</td>
										<td style="width: 35px;" >
											<input style="width: 30px;" type="text" name="hour" value="'.$job['hour'].'" />
										</td>
										<td style="width: 35px;" >
											<input style="width: 30px;" type="text" name="dayOfTheMonth" value="'.$job['dayOfTheMonth'].'" />
										</td>
										<td style="width: 35px;" >
											<input style="width: 30px;" type="text" name="month" value="'.$job['month'].'" />
										</td>
										<td style="width: 35px;" >
											<input style="width: 30px;" type="text" name="dayOfTheWeek" value="'.$job['dayOfTheWeek'].'" />
										</td>
										<td>
											'.get_action_selector($job['action'])."</td><td>".
											  get_server_selector($server_homes, $uniqueStr).'
										</td>
										<td style="width: 132px;">
											<input type="hidden" name="job_id" value=\''.$jobId.'\' />
											<input type="hidden" name="r_server_id" value=\''.$remote_server_id.'\' />
											<input style="" type="submit" name="editJob" value="'. get_lang("edit") .'" />
											<input style="" type="submit" name="removeJob" value="'. get_lang("remove") .'" />
											</form>
										</td>
									</tr>';
					}
				}
			}
		}
		echo $user_jobs;
?>
</table>
<?php
	}
	else
		echo "<h3>". get_lang("there_are_no_scheduled_jobs") ."</h3>";
?>
<table class='center hundred' ><tr><td><a href='javascript:history.go(-1)' > << <?php echo get_lang("back") ?></a><?php if(!$boolShowedAdminLink && $isAdmin){ echo '&nbsp; &nbsp; | &nbsp; &nbsp; ' . '<a href="home.php?m=cron&p=cron">' . get_lang('cron_admin_link_display_text') . '</a>'; }?></td></tr></table>
<script type="text/javascript">
$(document).ready(function() 
	{
		<?php echo $refresh->build("1000"); ?>
	}
);
</script>
<?php
}
?>