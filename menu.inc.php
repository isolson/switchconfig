<?php require_once('lang.php'); ?>

	<div id='topmenu'>
		<?php
		// Get username from web user session or legacy switch session
		$htmlUsername = '';
		if(isset($_SESSION['web_user']) && isset($_SESSION['web_user']['username'])) {
			$htmlUsername = htmlspecialchars($_SESSION['web_user']['username'], ENT_QUOTES, 'UTF-8');
		} elseif(isset($_SESSION['username'])) {
			$htmlUsername = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
		}
		$isWebUserAuthenticated = isset($_SESSION['web_user_authenticated']) && $_SESSION['web_user_authenticated'] === true;
		?>

		<div class='topmenu-left'>
			<a href='index.php' class='brand-link' title='<?php translate('Switch Manager'); ?>'>
				<span class='brand-icon'></span>
				<span class='brand-text'><?php translate('Switch Manager'); ?></span>
			</a>
		</div>

		<?php if(isset($MAINCTRLS) == false || (isset($MAINCTRLS) == true && $MAINCTRLS == true)) { ?>
		<div class='topmenu-center'>
			<a href='index.php' class='slubbutton secondary' id='mainmenubtn' title='<?php translate('Switch Monitoring'); ?>'><?php translate('Monitoring'); ?></a>
			<?php if(defined('ENABLE_CONFIG_BACKUP') && ENABLE_CONFIG_BACKUP) { ?>
				<a href='backup.php' class='slubbutton secondary' id='backupbtn' title='<?php translate('Backup switch configurations'); ?>'><?php translate('Backups'); ?></a>
			<?php } ?>
			<?php if($isWebUserAuthenticated) { ?>
				<a href='switches.php' class='slubbutton secondary' id='switchesbtn' title='<?php translate('Manage switches and credentials'); ?>'><?php translate('Switches'); ?></a>
				<a href='settings.php' class='slubbutton secondary' id='settingsbtn' title='<?php translate('App preferences and users'); ?>'><?php translate('Settings'); ?></a>
			<?php } ?>
			<?php if(!empty($ZOOM)) { ?>
			<script>
				function zoom(zoom) { document.body.style.zoom = zoom; }
			</script>
			<span id='zoomlinks'>
				<a href='#' class='slubbutton secondary notypo' onclick='zoom("50%");'>50%</a>
				<a href='#' class='slubbutton secondary notypo' onclick='zoom("80%");'>80%</a>
				<a href='#' class='slubbutton secondary notypo' onclick='zoom("100%");'>100%</a>
				<a href='#' class='slubbutton secondary notypo' onclick='zoom("120%");'>120%</a>
			</span>
			<?php } ?>
		</div>
		<?php } ?>

		<div class='topmenu-right'>
			<?php if(defined('ENABLE_PASSWORD_CHANGE') && ENABLE_PASSWORD_CHANGE) { ?>
				<a href='password.php' class='slubbutton secondary' id='pwchangebtn' title='<?php translate('Change switch password'); ?>'><?php translate('Password'); ?></a>
			<?php } ?>
			<?php if($htmlUsername != '') { ?>
				<a href='login.php?logout=1' class='slubbutton destructive' id='logoutbtn'><?php echo str_replace('%USER%', $htmlUsername, translate('Log Out %USER%',false)); ?></a>
			<?php } ?>
		</div>
	</div>
