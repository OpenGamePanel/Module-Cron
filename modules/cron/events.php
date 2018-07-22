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

function exec_ogp_module() 
{	
	// Using the refreshed class
	if( isset($_GET['get_cronevents']) )
	{
		require_once('includes/lib_remote.php');
		global $db;
		$remote_server_id = $_GET['r_server_id'];
		$remote_server = $db->getRemoteServer($remote_server_id);
		$remote = new OGPRemoteLibrary($remote_server['agent_ip'], $remote_server['agent_port'], $remote_server['encryption_key'], $remote_server['timeout'] );
		if($remote->status_chk() != 1)
		{
			print_failure(get_lang("agent_offline"));
			return;
		}
		$remote->remote_readfile('scheduler.log',$events);
		if ($events != "")
			echo "<pre class='log' >$events</pre>";
		else
			echo "<pre class='log' >Log empty</pre>";
	}
	else
	{
		require_once("includes/refreshed.php");
		
		echo "<h2>".get_lang('cron_events')."</h2>";
		
		$control = '<form method="POST" >
					<input type="submit" name="';
		if( isset( $_POST['full'] ) )
		{
			$height = "100%";
			$control .= 'default" value="-';
		}	
		else
		{
			$height = "500px";
			$control .= 'full" value="+';
		}
		$control .= '" /></form><br />';
		
		$intervals = array( "4s" => "4000",
							"8s" => "8000",
							"30s" => "30000",
							"2m" => "120000",
							"5m" => "300000" );
							
		$intSel = '<form action="" method="GET" >
				   <input type="hidden" name="m" value="cron" />
				   <input type="hidden" name="p" value="events" />
				   <input type="hidden" name="r_server_id" value="'.$_GET['r_server_id'].'" />'.
				   get_lang('refresh_interval').
				   ':<select name="setInterval" onchange="this.form.submit();">';
		
		foreach ($intervals as $interval => $value )
		{
			$selected = "";
			if ( isset( $_GET['setInterval'] ) AND $_GET['setInterval'] == $value )
				$selected = 'selected="selected"';
			$intSel .= '<option value="'.$value.'" '.$selected.' >'.$interval.'</option>';
		}					
		$intSel .= "</select></form>";
								
		$setInterval = isset( $_GET['setInterval'] ) ? $_GET['setInterval'] : 4000;
		$refresh = new refreshed();
		$pos = $refresh->add("home.php?m=cron&p=events&type=cleared&get_cronevents&r_server_id=".$_REQUEST['r_server_id']);
		echo $refresh->getdiv($pos,"height:".$height.";overflow:auto;max-width:1600px;");
		?><script type="text/javascript">$(document).ready(function(){ <?php echo $refresh->build("$setInterval"); ?>} ); </script><?php
		echo "<table class='center' ><tr><td>$intSel</td><td>$control</td></tr></table>";
		echo create_back_button( $_GET['m'], 'cron' );
	}
}
?>