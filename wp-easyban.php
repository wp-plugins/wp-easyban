<?php
/*
Plugin Name: EasyBan
Plugin URI: http://meandmymac.net/plugins/easyban/
Description: Advanced blocking and banning of visitors. Use this plugin to make a website private or block IP addresses, hostnames, domains and extensions from both visitors and referers. 
Author: Arnan de Gans
Version: 1.3.2
Author URI: http://meandmymac.net/
*/

#---------------------------------------------------
# Load other plugin files and values
#---------------------------------------------------
include_once(ABSPATH.'wp-content/plugins/wp-easyban/wp-easyban-template.php');
easyban_check_config();

# ---------------------------------------------------
# Only proceed with the plugin if MySQL Tables are setup properly
# ---------------------------------------------------
if (easyban_mysql_table_exists()) {

	add_action('admin_menu', 'easyban_dashboard', 1); //Add page menu links
	add_action('template_redirect', 'easyban_header'); // Check if banned or logged in and redirect if needed

 	if($easyban_config['length'] == "on") {
		easyban_remove_expired();
	}

	if ( isset($_POST['easyban_submit']) AND $_GET['new'] == "true")
		add_action('init', 'easyban_insert_input'); //New ban

	if ( isset($_GET['delete_ban']) )
		add_action('init', 'easyban_request_delete'); //Delete ban

	if ( isset($_POST['easyban_submit_options']) AND $_GET['updated'] == "true")
		add_action('init', 'easyban_options_submit'); //Update Options

	if ( isset($_POST['easyban_uninstall']) )
		add_action('init', 'easyban_plugin_uninstall'); //Uninstall

	// Load Options fetch remote information
	$easyban_config = get_option('easyban_config');
	$easyban_scriptname = basename(__FILE__);
	if(empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
		$easyban_remote_ip = $_SERVER["REMOTE_ADDR"];
	} else {
		$easyban_remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}
} else {
	// Install table if not existing
	easyban_mysql_install();
}

/* -------------------------------------------------------------
 Name:      easyban_mysql_install

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
------------------------------------------------------------- */
function easyban_mysql_install() {
	global $wpdb;
	
	$table_name = $wpdb->prefix . "banned";
	$sql = "CREATE TABLE ".$table_name." (
		id mediumint(8) unsigned NOT NULL auto_increment,
		address varchar(255) NOT NULL default '',
		range varchar(255) NOT NULL default '',
		reason varchar(255) NOT NULL default '',
		redirect varchar(255) NOT NULL default '',
		thetime int(15) NOT NULL,
		timespan int(15) NOT NULL,
		PRIMARY KEY (id)
		);";

	$wpdb->query($sql);

	if ( !easyban_mysql_table_exists()) {
		add_action('admin_menu', 'easyban_mysql_warning');
	}
}

/* -------------------------------------------------------------
 Name:      easyban_mysql_table_exists

 Purpose:   Check if the table exists in the database
 Receive:   -none-
 Return:	-none-
------------------------------------------------------------- */
function easyban_mysql_table_exists() {
	global $wpdb;
	
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $wpdb->prefix."banned") {
			return true;
		}
	}
	return false;
}

/* -------------------------------------------------------------
 Name:      easyban_mysql_warning

 Purpose:   Database errors if things go wrong
 Receive:   -none-
 Return:	-none-
------------------------------------------------------------- */
function easyban_mysql_warning() {
	echo '<div class="updated"><h3>WARNING! The MySQL table was not created! You cannot store bans. Seek support at meandmymac.net.</h3></div>';
}

/* -------------------------------------------------------------
 Name:      easyban_remove_expired

 Purpose:   Removes expired bans
 Receive:   -none-
 Return:	-none-
------------------------------------------------------------- */
function easyban_remove_expired() {
	global $wpdb;

	$SQL = "SELECT id, timespan FROM ".$wpdb->prefix."banned ORDER BY id asc";
	$old_bans = $wpdb->get_results($SQL);
	foreach($old_bans as $ban) {
		if($ban->timespan >= date("U")) {
			$delSQL = "DELETE FROM ".$wpdb->prefix."banned WHERE id = ".$ban->id;
			$wpdb->query($delSQL);
		}
	}
}

/* -------------------------------------------------------------
 Name:      easyban_header

 Purpose:   Checks if a user is banned or not
 Receive:   -none-
 Return:	-none-
------------------------------------------------------------- */
function easyban_header() {
	global $wpdb, $easyban_config, $easyban_remote_ip;

	if($easyban_config['status'] == "on") { 
	
		$referer = strtolower($_SERVER["HTTP_REFERER"]);			
		$remote_ip_long = sprintf("%u", ip2long($easyban_remote_ip));
		$remote_addr 	= gethostbyaddr($easyban_remote_ip);

		$SQL = "SELECT * FROM ".$wpdb->prefix."banned ORDER BY id asc";
		$bans = $wpdb->get_results($SQL);

		foreach($bans as $ban) {
			if(strlen($ban->redirect) > 0) {
				$redirect_to = $ban->redirect;	
			} else {
				$redirect_to = $easyban_config['redirect'];
			}
			
			if(strlen($ban->reason) > 0) {
				$banned_reason = $ban->reason;	
			} else {
				$banned_reason = "Not given";
			}
			
			if($ban->timespan > 0) { 
				$banned_until = date("l, j F Y", $ban->timespan);
			} else {
				$banned_until = "indefinite";
			}
	
			$referer_pattern = str_replace ('.', '\\.', $ban->address);
			$referer_pattern = str_replace ('*', '.+', $referer_pattern);
			$referer_pattern = "/$referer_pattern/i";
			
			if(($remote_ip_long >= $ban->address AND $remote_ip_long <= $ban->address) OR preg_match($referer_pattern, $referer)) {
				easyban_banned_template($banned_until, $banned_reason, $easyban_config['delay'], $redirect_to);
				exit;
			}
		}
	}
}

/* ============================================
   ADMIN PANEL OPTIONS, DO NOT EDIT BELOW HERE!
============================================ */

/* -------------------------------------------------------------
 Name:      easyban_insert_input

 Purpose:   Insert the new ban into the database
 Receive:   -None-
 Return:	-None-
------------------------------------------------------------- */
function easyban_insert_input() {
	global $wpdb, $easyban_remote_ip;

	$type	 	= htmlentities(trim($_POST['type'], "\t\n "), ENT_QUOTES);
	$address 	= htmlentities(trim($_POST['address'], "\t\n "), ENT_QUOTES);
	$range	 	= htmlentities(trim($_POST['range'], "\t\n "), ENT_QUOTES);
	$reason 	= htmlentities(trim($_POST['reason'], "\t\n "), ENT_QUOTES);
	$timetype 	= htmlentities(trim($_POST['timetype'], "\t\n "), ENT_QUOTES);
	$redirect 	= htmlentities(trim($_POST['redirect'], "\t\n "), ENT_QUOTES);
	$timeset 	= htmlentities(trim($_POST['timeset'], "\t\n "), ENT_QUOTES);

	if(strlen($address)!=0 AND strlen($address)<=255 AND strlen($redirect)<=255 AND strlen($reason)<=255 AND strlen($range)<=15) {
		if($timetype == "d") {
			$timespan = ($timeset * 86400) + date("U");
		} else if($timetype == "w") {
			$timespan = ($timeset * 604800) + date("U");
		} else {
			$timespan = 0;
		}

		$reserved = array("127.0.0.1", "0.0.0.0", "localhost", "::1", $easyban_remote_ip);

		if(!in_array(strtolower($address), $reserved) AND !in_array(strtolower($range), $reserved)) {		
			if($type == "range") {
				$address 	= gethostbyname($address);
				$range 		= gethostbyname($range);
				$address 	= sprintf("%u", ip2long($address));
				$range 		= sprintf("%u", ip2long($range));
			} else if($type == "single") {
				$address 	= gethostbyname($address);
				$range 		= gethostbyname($address);
				$address 	= sprintf("%u", ip2long($address));
				$range 		= sprintf("%u", ip2long($address));
			} else if($type == "referer") {
				$address 	= strtolower($address);
				$range		= strtolower($address);
			} else {
				wp_redirect('edit.php?page=wp-easyban&false_return=true&reason=t');			
			}
		
			$postquery = "INSERT INTO
			".$wpdb->prefix."banned
			(address, range, reason, redirect, thetime, timespan)
			VALUES
			('$address', '$range', '$reason', '$redirect', '".date("U")."', '$timespan')
			";
			if($wpdb->query($postquery)) {
				wp_redirect('plugins.php?page=wp-easyban2&new_return=true');
			} else {
				die(mysql_error());
			}
		} else {
			wp_redirect('edit.php?page=wp-easyban&false_return=true&reason=r');
		}

	} else {
		wp_redirect('edit.php?page=wp-easyban&false_return=true&reason=f');			
	}
}


/* -------------------------------------------------------------
 Name:      easyban_request_delete

 Purpose:   Remove ban from database
 Receive:   -none-
 Return:    boolean
------------------------------------------------------------- */
function easyban_request_delete() {
	global $userdata, $wpdb;

	$ban_id = $_GET['delete_ban'];
	if($ban_id > 0) {
		$SQL = "SELECT
		".$wpdb->prefix."banned.id,
		".$wpdb->prefix."users.display_name as display_name
		FROM
		".$wpdb->prefix."banned,
		".$wpdb->prefix."users
		WHERE
		".$wpdb->prefix."banned.id = '$ban_id'";

		$ban = $wpdb->get_row($SQL);

		if ($userdata->user_level >= 10) {
			if(easyban_delete_banid($ban_id) == TRUE) {
				wp_redirect('plugins.php?page=wp-easyban2&deleted_return=true');
			} else {
				die(mysql_error());
			}
		}
	}
}

/* -------------------------------------------------------------
 Name:      easyban_delete_banid

 Purpose:   Remove ban from database
 Receive:   $ban_id
 Return:    boolean
------------------------------------------------------------- */
function easyban_delete_banid ($ban_id) {
	if($ban_id > 0) {
		global $wpdb;

		$SQL = "DELETE FROM ".$wpdb->prefix."banned WHERE id = $ban_id";
		if(!$wpdb->query($SQL)) {
			die(mysql_error());
		} else {
			return TRUE;
		}
	}
}

/* -------------------------------------------------------------
 Name:      easyban_dashboard

 Purpose:   Add pages to admin menus
 Receive:   -none-
 Return:    -none-
------------------------------------------------------------- */
function easyban_dashboard() {
	add_submenu_page('edit.php', 'EasyBan > Add', 'Add Ban', 'manage_options', 'wp-easyban', 'easyban_dashboard_add');
	add_submenu_page('plugins.php', 'EasyBan > Manage', 'Manage Bans', 'manage_options', 'wp-easyban2', 'easyban_dashboard_manage');
	add_submenu_page('options-general.php', 'EasyBan > Settings', 'EasyBan', 'manage_options', 'wp-easyban3', 'easyban_dashboard_options');
}

/* -------------------------------------------------------------
 Name:      easyban_dashboard_manage

 Purpose:   Admin management page
 Receive:   -none-
 Return:    -none-
------------------------------------------------------------- */
function easyban_dashboard_manage() {
	global $wpdb, $easyban_config, $easyban_remote_ip;

	if ($_GET['new_return'] == "true") : ?>
		<div id="message" class="updated fade"><p><?php _e('Ban <strong>added</strong>. Please check what you did!') ?></p>
		</div>
	<?php endif; ?>
	<?php if ($_GET['deleted_return'] == "true") : ?>
		<div id="message" class="updated fade"><p><?php _e('Ban <strong>deleted</strong>.') ?></p>
		</div>
	<?php endif; ?>

	<div class="wrap">
  	<h2>EasyBan management</h2>
		<?php
		$bans = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "banned ORDER BY thetime asc");
		?>
		<table id="the-list-x" class="form-table">
		  <tr>
	    	<th scope="col">Victims</th>
		    <th scope="col">Reason</th>
		    <th scope="col">Redirect to</th>
			<th scope="col">Date set</th>
			<th scope="col">Expiry date</th>
			<th scope="col">&nbsp;</th>
		  </tr>
		<?php if ($bans) {
			foreach($bans as $ban) {
				if(is_numeric($ban->address) and is_numeric($ban->range)) { 
					$ban->address	= long2ip($ban->address); 
					$ban->range		= long2ip($ban->range);
				} else {
					$ban->address	= $ban->address;
					$ban->range		= $ban->range;
				}
				if($ban->address == $ban->range) {
					$final_address = $ban->address;
				} else {
					$final_address = $ban->address." - ".$ban->range;
				}
				$class = ('alternate' != $class) ? 'alternate' : ''; ?>
			  	<tr id='ban-<?php echo $ban->id; ?>' class='<?php echo $class; ?>'>
					<td><?php echo $final_address; ?></td>
					<td><?php echo $ban->reason; ?></td>
					<td><?php echo $ban->redirect; ?></td>
					<td><?php echo date("M d, Y H:i", $ban->thetime); ?></td>
					<td><?php if($ban->timespan > 0) {echo date("M d, Y H:i", $ban->timespan); } else { echo 'Never'; } ?></td>
					<td><a href="<?php echo get_option('home').'/wp-admin/plugins.php?page=wp-easyban2&amp;delete_ban='.$ban->id;?>" class="delete" onclick="return confirm('You are about to delete this ban \'<?php echo $ban->address;?>\'\n  \'OK\' to delete, \'Cancel\' to stop.')">Delete</a></td>
			  	</tr>
			<?php }
		 } else { ?>
			<tr><td colspan="6">No bans set.</td></tr>
		<?php }	?>
		</table>
	</div>
<?php
}

/* -------------------------------------------------------------
 Name:      easyban_dashboard_add

 Purpose:   Add a ban
 Receive:   -none-
 Return:    -none-
------------------------------------------------------------- */
function easyban_dashboard_add() {
	global $wpdb, $easyban_config, $easyban_remote_ip;

	if ($_GET['false_return'] == "true") : ?>
		<?php if ($_GET['reason'] == "t") { ?>
			<div id="message" class="updated fade"><p><?php _e('Ban could <strong>not</strong> be added. No banning method selected!') ?></p></div>
		<?php } else if ($_GET['reason'] == "f") { ?>
			<div id="message" class="updated fade"><p><?php _e('Ban could <strong>not</strong> be added. Not all fields matched the criteria!') ?></p></div>
		<?php } else if ($_GET['reason'] == "r") { ?>
			<div id="message" class="updated fade"><p><?php _e('Ban could <strong>not</strong> be added. You cannot block yourself, 127.0.0.1, ::1 or localhost!') ?></p></div>
		<?php } ?>
	<?php endif; ?>

	<div class="wrap">
		<h2>Add ban</h2>
	  	<form method="post" action="edit.php?page=wp-easyban&amp;new=true">
	    	<input type="hidden" name="easyban_submit" value="true" />
	    	<table class="form-table">
		      	<tr valign="top">
			        <th scope="row">You:</th>
			        <td>IP: <?php echo $easyban_remote_ip; ?><br />Hostname: <?php echo gethostbyaddr($easyban_remote_ip); ?></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Select method:</th>
			        <td><input name="type" type="radio" value="single" checked="checked" /> Single IP or hostname<br /><input name="type" type="radio" value="range" /> IP Range<br /><input name="type" type="radio" value="referer" /> Referer block</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Address/Range:</th>
			        <td><input name="address" type="text" size="20" /> - <input name="range" type="text" size="20" /> <em>(IP address, domain or hostname)</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Possibilities:</th>
			        <td>- Single IP/Hostname: fill in either a hostname or IP address in the first field.<br />
			        - IP Range: Put the starting IP address in the left and the ending IP address in the right field.<br />
			        - Referer block: To block google.com put google.com in the first field. To block google altogether put google in the field.</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Reason:</th>
			        <td><input name="reason" type="text" size="50" maxlength="255"/> <em>(optional, shown to victim)</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Redirect to:</th>
			        <td><input name="redirect" type="text" size="50" maxlength="255" /> <em>(optional)</em></td>
		      	</tr valign="top">
		      	<?php if($easyban_config['length'] == "on") { ?><tr>
			        <th  scope="row">How long:</th>
			        <td><select name="timetype">
						<option value="p">permanent</option>
						<option value="d">day(s)</option>
						<option value="w">week(s)</option>
					</select> <input name="timeset" type="text" size="6" /><br /><em>Leave field empty when using permanent. Fill in a number higher than 0 when using another option!</em></td>
		      	</tr><?php } ?>
		      	<tr valign="top">
			        <th scope="row">Hints and tips:</th>
			        <td>
						- Banning hosts in the 10.x.x.x / 169.254.x.x / 172.16.x.x or 192.168.x.x range probably won't work.<br />
						- Banning by internet hostname might work unexpectedly and resulting in banning multiple people from the same ISP!<br />
						- Wildcards on IP addresses are allowed. Block 84.234.*.* to block the whole 84.234.x.x range!<br />
						- Setting a ban on a range of IP addresses might work unexpected and can result in false positives!<br />
						- An IP address <strong>always</strong> contains 4 parts with numbers no higher than 254 separated by a dot!<br />
						- If a ban does not seem to work try to find out if the person you're trying to ban doesn't use <a href="http://en.wikipedia.org/wiki/DHCP" target="_blank" title="Wikipedia - DHCP, new window">DHCP</a>.<br />
						- A temporary ban is automatically removed when it expires.<br />
						- To block a domain you can use keywords. Just blocking 'meandmymac' would work almost the same as blocking 'meandmymac.net'. However, when putting just 'meandmymac', <strong>ALL</strong> extensions (.com .net .co.ck. co.uk etc.) are blocked!!<br />
						- For more questions please seek help at my <a href="http://forum.at.meandmymac.net/" target="_blank" title="EasyBan support, new window">support pages</a>.<br />
			        </td>
		      	</tr>
	    	</table>
	    	<p class="submit">
	      		<input type="submit" name="Submit" value="Save new ban &raquo;" />
	    	</p>
	  	</form>
	</div>
<?php
}

/* -------------------------------------------------------------
 Name:      easyban_dashboard_options

 Purpose:   Admin options page
 Receive:   -none-
 Return:    -none-
------------------------------------------------------------- */
function easyban_dashboard_options() {
	$easyban_config = get_option('easyban_config');
?>
	<div class="wrap">
	  	<h2>Ban options</h2>
	  	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>&amp;updated=true">
	    	<input type="hidden" name="easyban_submit_options" value="true" />
		    <table class="form-table">
		      	<tr>
			        <th scope="row">Activate:</th>
			        <td><select name="status">';
					<?php if($easyban_config['status'] == "on") { ?>
				        <option value="on">enabled</option>
						<option value="off">disabled</option>
					<?php } else { ?>
						<option value="off">disabled</option>
				        <option value="on">enabled</option>
					<?php } ?>
					</select></td>
		      	</tr>
		      	<tr>
			        <th scope="row">Enable timed bans:</th>
			        <td><select name="length">';
					<?php if($easyban_config['length'] == "on") { ?>
				        <option value="on">enabled</option>
						<option value="off">disabled</option>
					<?php } else { ?>
						<option value="off">disabled</option>
				        <option value="on">enabled</option>
					<?php } ?>
					</select></td>
		      	</tr>
		      	<tr>
			        <th scope="row">Redirect to:</th>
			        <td><input name="redirect" type="text" value="<?php echo $easyban_config['redirect'];?>" size="50" /> (include 'http://')</td>
		      	</tr>
		      	<tr>
			        <th scope="row">Redirect delay:</th>
			        <td><input name="delay" type="text" value="<?php echo $easyban_config['delay'];?>" size="4" /> (default: 3 sec)</td>
		      	</tr>
	    	</table>
		    <p class="submit">
		      	<input type="submit" name="Submit" value="Update Options &raquo;" />
		    </p>
		</form>

	  	<h2>Uninstall EasyBan</h2>
	  	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
		    <table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Banned installs a table in MySQL. When you disable the plugin the table will not be deleted. To delete the table use the button below.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">WARNING!</th>
			        <td><b style="color: #f00;">This process is irreversible and will delete ALL bans!</b></td>
				</tr>
			</table>
	  		<p class="submit">
		    	<input type="hidden" name="event_uninstall" value="true" />
		    	<input onclick="return confirm('You are about to uninstall the events plugin\n  All scheduled events will be lost!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" name="Submit" value="Uninstall Plugin &raquo;" />
	  		</p>
	  	</form>

	</div>
<?php 
}

/* -------------------------------------------------------------
 Name:      easyban_check_config

 Purpose:   Create or update the options
 Receive:   -none-
 Return:    -none-
------------------------------------------------------------- */
function easyban_check_config() {
	if ( !$option = get_option('easyban_config') ) {
		// Default Options
		$option['status'] 			= 'on';
		$option['redirect']			= 'http://www.google.com';
		$option['delay'] 			= '3';
		$option['length'] 			= 'on';
		update_option('easyban_config', $option);
	}

	// If value not assigned insert default (upgrades)
	if (strlen($option['status']) < 1 OR strlen($option['redirect']) < 1 OR strlen($option['delay']) < 1 OR strlen($option['length']) < 1 ) {
		$option['status'] 			= 'on';
		$option['redirect'] 		= 'http://www.google.com';
		$option['delay'] 			= '3';
		$option['length'] 			= 'on';
		update_option('easyban_config', $option);
	}
}

/* -------------------------------------------------------------
 Name:      easyban_options_submit

 Purpose:   Save options
 Receive:   -none-
 Return:    -none-
------------------------------------------------------------- */
function easyban_options_submit() {
	//options page
	$option['status'] 			= trim($_POST['status'], "\t\n ");
	$option['redirect'] 		= trim($_POST['redirect'], "\t\n ");
	$option['delay'] 			= trim($_POST['delay'], "\t\n ");
	$option['length'] 			= trim($_POST['length'], "\t\n ");
	update_option('easyban_config', $option);
}

/* -------------------------------------------------------------
 Name:      easyban_plugin_uninstall

 Purpose:   Delete the entire database table and remove the options on uninstall.
 Receive:   -none-
 Return:	-none-
------------------------------------------------------------- */
function easyban_plugin_uninstall() {
	global $wpdb;

	// Drop MySQL Tables
	$SQL = "DROP TABLE ".$wpdb->prefix."banned";
	mysql_query($SQL) or die("*oops*. An unexpected error occured.<br />".mysql_error());

	// Delete Option
	delete_option('easyban_config');

	// Deactivate Plugin
	$current = get_settings('active_plugins');
    array_splice($current, array_search( "wp-easyban/wp-easyban.php", $current), 1 );
	update_option('active_plugins', $current);
	do_action('deactivate_' . trim( $_GET['plugin'] ));

	wp_redirect('plugins.php?deactivate=true');

	die();

}
?>