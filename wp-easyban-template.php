<?php
/*
 * This file is the template for EasyBan. When a person gets redirected he/she sees this page.
 * You can use normal HTML but take note of the PHP code at the beginning and end of the file. THESE SHOULD NOT BE EDITED, MOVED or REMOVED!
 * A few values can be used within the template. Use the existing code as an example and alter to your needs if you want.
 *
 */

/* -------------------------------------------------------------
 Name:      easyban_banned_template

 Purpose:   Shows to people when banned.
 Receive:   $until, $reason, $delay, $url
 Return:	-None-
------------------------------------------------------------- */
function easyban_banned_template($until, $reason, $delay, $url) { ?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	
	<head>
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
		<title><?php bloginfo('name'); ?></title>
		<link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>" type="text/css" media="screen" />
		<meta name="generator" content="WordPress <?php bloginfo('version'); ?> - EasyBan plugin" /> <!-- leave this for stats -->
		<meta name="robots" content="noarchive" />
		<meta http-equiv="refresh" content="<?php echo $delay;?>; URL=<?php echo $url;?>">
	</head>
	<body>
	<!-- START -->
	<div id="page">
		<div id="header">
			<h1>You are not permitted to enter this website</h1>
		</div>
		<hr />
		<div id="content" class="widecolumn">
			<div class="post">
		        <div class="entrytext">
		        	<p>You have been banned until: <?php echo $until; ?>.</p>
					<p>Reason: <?php echo $reason;?></p>
					<p>You will be redirected to another website in <?php echo $delay; ?> seconds.</p>
				</div>
		    </div>
		</div>
		<!-- STOP -->
		<hr />
		<div id="footer">
			<p>
				<?php bloginfo('name'); ?> is proudly powered by <a href="http://wordpress.org/">WordPress</a><br />
				You are a victim of the <a href="http://meandmymac.net/plugins/easyban/" target="_blank">easyban plugin</a>.
			</p>
		</div>
	</div>
	</body>
	</html>
<?php
}
?>