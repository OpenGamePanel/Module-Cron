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
function exec_ogp_module() 
{
	global $db;
	$remote_server = $db->getRemoteServer($_GET['r_server_id']);
	$remote = new OGPRemoteLibrary( $remote_server['agent_ip'], $remote_server['agent_port'],
									$remote_server['encryption_key'], $remote_server['timeout'] );
	
	if($remote->status_chk() == 1)
	{
		list($i, $H, $d, $m, $w, $date_rfc_2822, $time_zone) = explode('|', date('i|H|d|n|N|r|e', $remote->shell_action('get_timestamp', '')));
		echo '<table class="center">'.
			 '<tr><td style="width: 35px;" >'.$i.
			 '</td><td style="width: 35px;" >'.$H.
			 '</td><td style="width: 35px;" >'.$d.
			 '</td><td style="width: 35px;" >'.$m.
			 '</td><td style="width: 35px;" >'.$w.
			 '</td><td></b>('.$date_rfc_2822.') '.$time_zone.
			 '</td></tr></table>';
	}
	else
		echo get_lang("agent_offline");
}
?>
