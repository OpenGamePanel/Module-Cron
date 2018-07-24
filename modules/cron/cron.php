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
require_once('includes/lib_remote.php');
require_once('modules/cron/shared_cron_functions.php');

function exec_ogp_module() 
{
	global $db, $view;
	$r_servers = $db->getRemoteServers();
	$homes = $db->getIpPorts();
	if(!$homes)
	{
		print_failure(get_lang('cron_admin_no_ogp_servers_to_display'));
		return 0;
	}
	
	foreach( $homes as $home )
	{
		$home['access_rights'] = "ufpet";
		$id = $home['home_id']."_".$home['ip']."_".$home['port'];
		$server_homes[$id] = $home;
	}
	
	foreach($r_servers as $r_server)
	{
		$id = $r_server['remote_server_id'];
		$remote_servers[$id] = $r_server;
	}
	
	updateCronJobsToNewApi();	
	
	list($jobsArray, $remote_servers_offline) = reloadJobs($server_homes, $remote_servers);
	
	if( isset($_POST['addJob']) or isset($_POST['editJob']) )
	{
		if(!checkCronInput($_POST['minute'], $_POST['hour'], $_POST['dayOfTheMonth'], $_POST['month'], $_POST['dayOfTheWeek']))
		{
			print_failure(get_lang('OGP_LANG_bad_inputs'));
			$view->refresh('?m=cron&p=cron',2);
			return;
		}
		
		if(isset($_POST['homeid_ip_port']) and isset($server_homes[$_POST['homeid_ip_port']]))
		{
			$panelURL = getOGPSiteURL();
			if($panelURL === false)
			{
				print_failure('Failed to retrieve panel URL.');
				$view->refresh('?m=cron&p=cron',2);
				return;
			}
			
			$game_home = $server_homes[$_POST['homeid_ip_port']];
			$ip = $game_home['ip'];
			$port = $game_home['port'];
			$mod_key = $game_home['mod_key'];
			$token = $db->getApiToken($_SESSION['user_id']);
				
			switch ($_POST['action']) {
				case "stop":
					$command = "wget -qO- \"${panelURL}/ogp_api.php?gamemanager/stop&token=${token}&ip=${ip}&port=${port}&mod_key=${mod_key}\" --no-check-certificate > /dev/null 2>&1";
					break;
				case "start":
					$command = "wget -qO- \"${panelURL}/ogp_api.php?gamemanager/start&token=${token}&ip=${ip}&port=${port}&mod_key=${mod_key}\" --no-check-certificate > /dev/null 2>&1";
					break;
				case "restart":
					$command = "wget -qO- \"${panelURL}/ogp_api.php?gamemanager/restart&token=${token}&ip=${ip}&port=${port}&mod_key=${mod_key}\" --no-check-certificate > /dev/null 2>&1";
					break;
				case "steam_auto_update":
					$command = "wget -qO- \"${panelURL}/ogp_api.php?gamemanager/update&token=${token}&ip=${ip}&port=${port}&mod_key=${mod_key}&type=steam\" --no-check-certificate > /dev/null 2>&1";
					break;
			}
			
			$remote = new OGPRemoteLibrary( $game_home['agent_ip'], $game_home['agent_port'],
											$game_home['encryption_key'], $game_home['timeout'] );
		}
		else
		{
			$r_server_id = $_POST['r_server_id'];
			$remote = new OGPRemoteLibrary( $remote_servers[$r_server_id]['agent_ip'],
											$remote_servers[$r_server_id]['agent_port'],
											$remote_servers[$r_server_id]['encryption_key'],
											$remote_servers[$r_server_id]['timeout']);
			$command = strip_real_escape_string($_POST['command']);
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
	elseif( isset($_POST['removeJob']) and isset($remote_servers[$_POST['r_server_id']]) and isset($jobsArray[$_POST['r_server_id']][$_POST['job_id']]) )
	{	
		$remote = new OGPRemoteLibrary( $remote_servers[$_POST['r_server_id']]['agent_ip'],
										$remote_servers[$_POST['r_server_id']]['agent_port'],
										$remote_servers[$_POST['r_server_id']]['encryption_key'],
										$remote_servers[$_POST['r_server_id']]['timeout']);
		$remote->scheduler_del_task($_POST['job_id']);
		list($jobsArray, $remote_servers_offline) = reloadJobs($server_homes, $remote_servers);
	}	
	
	echo "<h2>" . get_lang("schedule_new_job") . "</h2>";
	require_once("includes/refreshed.php");
	$refresh = new refreshed();
	if( isset($_POST['r_server_id']) )
	{
		foreach($server_homes as $key => $server_home)
		{
			if($server_home['remote_server_id'] == $_POST['r_server_id'])
			{
				$homeid_ip_port_by_r_server_id = $key;
				break;
			}
		}
	}

	$homeid_ip_port = isset($_POST['r_server_id']) ? ( isset($homeid_ip_port_by_r_server_id) ? $homeid_ip_port_by_r_server_id : 0 ) :
					  ( isset( $_POST['homeid_ip_port'] ) ? $_POST['homeid_ip_port'] :  key($server_homes) );
	$r_server_id = $homeid_ip_port == 0 ? $_POST['r_server_id'] : $server_homes[$homeid_ip_port]['remote_server_id'];
	$homeid_ip_port = $homeid_ip_port == 0 ? key($server_homes) : $homeid_ip_port;
	$curtime = $refresh->add( "home.php?m=cron&p=thetime&r_server_id=$r_server_id&type=cleared" );
	echo "<pre class='log' ><table><tr><td>" . get_lang("now") . 
		 "&nbsp;</td><td><form action='' method='POST' >" . get_server_selector($server_homes, $homeid_ip_port, TRUE, true) . 
		 "</form></td><td><form action='' method='POST' >" .
		 get_remote_server_selector($remote_servers, $remote_servers_offline, $r_server_id, TRUE) .
		 "</form></td></tr></table> <b style='font-size:1.4em;'>" . $refresh->getdiv($curtime) . "</b></pre>";
?>
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
			<form method="POST" >
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
			<?php echo get_action_selector(false, $server_homes, $homeid_ip_port);?>
		</td>
		<td>
			<?php echo get_server_selector($server_homes, $homeid_ip_port, true, true);?>
		</td>
		<td>
			<input style="" type="submit" name="addJob" value="<?php echo get_lang("add"); ?>" />
			</form>
		</td>
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
		<?php echo get_lang("server"); ?>
		</th>
		<th>
		<?php echo get_lang("command"); ?>
		</th>
	</tr>
	<tr>
		<td style="width: 35px;" >
			<form method="POST" >
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
		<td style="width: 100px;" >
			<?php echo get_remote_server_selector($remote_servers, $remote_servers_offline);?>
		</td>
		<td>
			<input style="width: 100%; box-sizing: border-box;" type="text" name="command" />
		</td>
		<td>
			<input style="" type="submit" name="addJob" value="<?php echo get_lang("add"); ?>" />
			</form>
		</td>
	</tr>
</table>
<br>
<h2><?php echo get_lang("scheduled_jobs");?></h2>
<?php
	if ( !empty($remote_servers_offline) )
	{
		$offline_servers = server . " (" . offline . "):";
		foreach($remote_servers_offline as $remote_server_offline)
		{
			$offline_servers .= " " . $remote_server_offline['remote_server_name'] . ",";
		}
		print_failure(rtrim($offline_servers, ","));
	}
	if ( !empty($jobsArray) )
	{
?>
<table class="center hundred">
	<tr>
		<td colspan='6' style="text-align:left;" >
			<form  action='' method='GET'>
				<input type="hidden" name="m" value="cron" />
				<input type="hidden" name="p" value="events" />
				<label for="r_server_id" ><?php echo get_lang("cron_events");?></label>
				<?php echo get_remote_server_selector($remote_servers, $remote_servers_offline, FALSE, TRUE, TRUE); ?>
			</form>
		</td>
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
		<?php echo get_lang("action") . " / " . get_lang("server"); ?>
		</th>
		<th>
		<?php echo get_lang("user_games") . " / " . get_lang("command"); ?>
		</th>
	</tr>
<?php
		$user_jobs = "";
		foreach( $jobsArray as $remote_server_id => $jobs )
		{
			foreach($jobs as $jobId => $job)
			{
				if(isset($job['action'])){	
					if(array_key_exists('home_id', $job) && array_key_exists('ip', $job) && array_key_exists('port', $job) && hasValue($job['home_id']) && hasValue($job['ip']) && hasValue($job['port'])){
						$idStr = $job['home_id']."_".$job['ip']."_".$job['port'];				
					}else{
						$idStr = false;
					}
								
					$task = get_action_selector($job['action'], $server_homes, $idStr)."</td><td>".
							get_server_selector($server_homes, $idStr, FALSE, TRUE);
				}
				else
					$task = get_remote_server_selector($remote_servers, $remote_servers_offline, $remote_server_id).
							'</td><td><input style="width: 100%; box-sizing: border-box;" type="text" name="command" value="'.str_replace("\"","&quot;",$job['command']).'" />';
				
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
									<td style="width: 100px;" >
										'.$task.'
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
		echo $user_jobs;
?>
</table>
<?php
	}
	else
		echo "<h3>". get_lang("there_are_no_scheduled_jobs") ."</h3>";

	echo '<table class="center hundred" ><tr><td><a href="home.php?m=cron&p=user_cron">'.
		 get_lang('back').'</a></td></tr></table>';
?>
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
