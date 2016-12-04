<script type="text/javascript" src="js/jquery/jquery-1.11.0.min.js"></script>
<?php
/*
 *
 * OGP - Open Game Panel
 * Copyright (C) Copyright (C) 2008 - 2012 The OGP Development Team
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
function reloadJobs($server_homes, $remote_servers)
{
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
					$parts = explode(" ", $job);
					$minute = $parts[0];
					$hour = $parts[1];
					$dayOfTheMonth = $parts[2];
					$month = $parts[3];
					$dayOfTheWeek = $parts[4];
					unset($parts[0],$parts[1],$parts[2],$parts[3],$parts[4]);
					$command = implode(" ", $parts);
					$retval = preg_match_all("/^%ACTION=(start|restart|stop)\|%\|(.*)$/", $command, $job_info );
					if($retval and !empty($job_info[1][0]))
					{
						//print_r($job_info);
						$action = $job_info[1][0];
						$server_args = explode("|%|", $job_info[2][0]);
						switch ($action) {
							case 'start':
								list($home_id, $home_path, $server_exe, $run_dir,
									 $startup_cmd, $port, $ip, $cpu, $nice) = $server_args;
								break;
							case 'restart':
								list($home_id, $ip, $port, $control_protocol, 
									 $control_password, $control_type, $home_path, 
									 $server_exe, $run_dir, $startup_cmd, $cpu, $nice) = $server_args;
								break;
							case 'stop':
								list($home_id, $ip, $port, $control_protocol, 
									 $control_password, $control_type, $home_path) = $server_args;
								break;
						}
						if(!isset($server_homes[$home_id."_".$ip."_".$port])) continue;
						$jobsArray[$rhost_id][$jobId] = array( 'job' => $job, 
															   'minute' => $minute, 
															   'hour' => $hour, 
															   'dayOfTheMonth' => $dayOfTheMonth, 
															   'month' => $month, 
															   'dayOfTheWeek' => $dayOfTheWeek,
															   'action' => $action,
															   'home_id' => $home_id,
															   'ip' => $ip,
															   'port' => $port);
					}
				}
			}
		}
	}
	return array($jobsArray, $remote_servers_offline);
}

function get_action_selector($action = false) {
	$server_actions = array('restart','stop','start');
	$select_action = '<select  style="width: 100px;" name="action">';
	foreach($server_actions as $server_action)
	{
		$selected = ($action and $action == $server_action) ? 'selected="selected"' : '';
		$select_action .= '<option value="'.$server_action.'" '.$selected.'>'.get_lang($server_action).'</option>';
	}
	return $select_action .= '</select>';
}

function get_server_selector($server_homes, $homeid_ip_port = FALSE, $onchange = FALSE) {
	$onchange_this_form_submit = $onchange ? 'onchange="this.form.submit();"' : '';
	$select_game = "<select style='text-overflow: ellipsis; width: 100%;' name='homeid_ip_port' $onchange_this_form_submit>\n";
	if($server_homes != FALSE)
	{
		foreach ( $server_homes as $server_home )
		{
			$selected = ($homeid_ip_port and $homeid_ip_port == $server_home['home_id']."_".$server_home['ip']."_".$server_home['port']) ? 'selected="selected"' : '';
			$select_game .= "<option value='". $server_home['home_id'] . "_" . $server_home['ip'] .
							"_" . $server_home['port'] . "' $selected>" . $server_home['home_name'] . 
							" - " . $server_home['ip'] . ":" .$server_home['port'] . "</option>\n";
		}
	}
	return $select_game .= "</select>\n";
}

function get_remote_server_selector($r_servers, $remote_servers_offline, $remote_server_id = FALSE, $onchange = FALSE) {
	$onchange_this_form_submit = $onchange ? 'onchange="this.form.submit();"' : '';
	$select_rserver = "<select  style='width: 100px;' name='r_server_id' $onchange_this_form_submit>\n";
	foreach ( $r_servers as $r_server )
	{
		$selected = ($remote_server_id and $remote_server_id == $r_server['remote_server_id']) ? 'selected="selected"' : '';
		$offline = isset($remote_servers_offline[$r_server['remote_server_id']]) ? ' (' . offline . ')' : '';
		$select_rserver .= "<option value='". $r_server['remote_server_id'] . "' $selected>" . $r_server['remote_server_name'] . "$offline</option>\n";
	}
	return $select_rserver .= "</select>\n";
}

function exec_ogp_module() 
{
	global $db;
	$homes = $db->getIpPortsForUser($_SESSION['user_id']);
	if(!$homes)
	{
		print_failure('There are no game servers assigned');
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
			$startup_cmd = get_sart_cmd($remote,$server_xml,$game_home,$game_home['mod_id'],$game_home['ip'],$game_home['port']);
			$cpu = $game_home['cpu_affinity'];
			$nice = $game_home['nice'];
			
			switch ($_POST['action']) {
				case "stop":
					$command = "%ACTION=stop|%|$home_id|%|$ip|%|$port|%|".
							   "$control_protocol|%|$control_password|%|$control_type|%|$home_path";
					break;
				case "start":
					$command = "%ACTION=start|%|$home_id|%|$home_path|%|$server_exe|%|$run_dir|%|".
							   "$startup_cmd|%|$port|%|$ip|%|$cpu|%|$nice";
					break;
				case "restart":
					$command = "%ACTION=restart|%|$home_id|%|$ip|%|$port|%|$control_protocol|%|".
							   "$control_password|%|$control_type|%|$home_path|%|$server_exe|%|$run_dir|%|".
							   "$startup_cmd|%|$cpu|%|$nice";
					break;
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

	echo "<h2>" . schedule_new_job . "</h2>";
	require_once("includes/refreshed.php");
	$refresh = new refreshed();
	$homeid_ip_port = isset($_POST['homeid_ip_port']) ? $_POST['homeid_ip_port'] : key($server_homes);
	$r_server_id = $server_homes[$homeid_ip_port]['remote_server_id'];
	$curtime = $refresh->add( "home.php?m=cron&p=thetime&r_server_id=$r_server_id&type=cleared" );
	echo "<pre class='log' ><table><tr><td>" . now . 
		"&nbsp;</td><td><form action='' method='POST' >" . get_server_selector($server_homes, $homeid_ip_port, TRUE) . 
		"</form></td></tr></table> <b style='font-size:1.4em;'>" . $refresh->getdiv($curtime) . "</b></pre>";
?>
<form method="POST" >	
<table class="center">
	<tr>
		<th>
		<?php echo minute; ?>
		</th>
		<th>
		<?php echo hour; ?>
		</th>
		<th>
		<?php echo day; ?>
		</th>
		<th>
		<?php echo month; ?>
		</th>
		<th>
		<?php echo day_of_the_week; ?>
		</th>
		<th>
		<?php echo action; ?>
		</th>
		<th>
		<?php echo user_games; ?>
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
		<td>
			<input style="width:60px;" type="submit" name="addJob" value="<?php echo add; ?>" />
		</td>
	</tr>
</table>
</form>
<br>
<h2><?php echo scheduled_jobs;?></h2>
<?php
	if ( !empty($jobsArray) )
	{
?>
<table class="center">
	</tr>
	<tr>
		<th>
		<?php echo minute; ?>
		</th>
		<th>
		<?php echo hour; ?>
		</th>
		<th>
		<?php echo day; ?>
		</th>
		<th>
		<?php echo month; ?>
		</th>
		<th>
		<?php echo day_of_the_week; ?>
		</th>
		<th>
		<?php echo action; ?>
		</th>
		<th>
		<?php echo user_games; ?>
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
											  get_server_selector($server_homes, $job['home_id']."_".$job['ip']."_".$job['port']).'
										</td>
										<td>
											<input type="hidden" name="job_id" value=\''.$jobId.'\' />
											<input type="hidden" name="r_server_id" value=\''.$remote_server_id.'\' />
											<input style="width:60px;" type="submit" name="editJob" value="'. edit .'" />
											<input style="width:60px;" type="submit" name="removeJob" value="'. remove .'" />
											</form>
										</td>
									</tr>';
				}
			}
		}
		echo $user_jobs;
?>
</table>
<?php
	}
	else
		echo "<h3>". there_are_no_scheduled_jobs ."</h3>";
?>
<table class='center' ><tr><td><a href='?m=administration&p=main' > << <?php echo back ?></a></td></tr></table>
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
