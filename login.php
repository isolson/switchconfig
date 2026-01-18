<?php

require_once('config.php');
require_once('lang.php');
require_once('php/session-options.php');
require_once('php/auth.php');
require_once('php/switchmanagement.php');

// Check if setup is required
if (needsSetup()) {
	header('Location: setup.php');
	exit();
}

$info = '';
$infoClass = '';

if(isset($_SESSION['web_user_authenticated']) && isset($_GET['logout'])) {

	// Sign out web user
	endWebSession();
	// Also clear switch credentials
	unset($_SESSION['username']);
	unset($_SESSION['password']);
	session_unset();
	session_destroy();
	$infoClass = 'ok';
	$info = translate('Successfully logged out', false);

} elseif(isset($_POST['username']) && isset($_POST['password'])) {

	// Try web user authentication first
	$webUser = authenticateWebUser($_POST['username'], $_POST['password']);

	if ($webUser !== false) {
		// Web authentication successful
		startWebSession($webUser);

		// Try to auto-authenticate switch credentials if stored
		$switchCreds = getDefaultSwitchCredentials();
		if ($switchCreds !== null) {
			// Test if credentials work on a switch
			$switches = getMergedSwitches();
			$authSuccess = false;
			foreach ($switches as $s) {
				$connection = @ssh2_connect($s['addr'], 22);
				if ($connection !== false) {
					if (@ssh2_auth_password($connection, $switchCreds['username'], $switchCreds['password']) !== false) {
						$_SESSION['username'] = $switchCreds['username'];
						$_SESSION['password'] = $switchCreds['password'];
						$_SESSION['last_activity'] = time();
						$authSuccess = true;
					}
					break;
				}
			}
		}

		// Redirect to desired page
		$redirect = 'index.php';
		if(!empty($_GET['redirect'])
		&& substr($_GET['redirect'], 0, 1) === '/') {
			$redirect = $_GET['redirect'];
		}
		header('Location: '.$redirect);
		die('Happy configuring!');

	} else {
		// Web auth failed, try legacy switch-only auth
		// This supports backward compatibility when users haven't set up web auth
		$switches = getMergedSwitches();
		$switchAuthSuccess = false;

		foreach($switches as $s) {
			$connection = @ssh2_connect($s['addr'], 22);
			if($connection !== false) {
				if(@ssh2_auth_password($connection, $_POST['username'], $_POST['password']) !== false) {
					// Switch auth OK - but only if web users don't exist
					if (!isDatastoreInitialized()) {
						$_SESSION['username'] = $_POST['username'];
						$_SESSION['password'] = $_POST['password'];
						$_SESSION['last_activity'] = time();

						$redirect = 'index.php';
						if(!empty($_GET['redirect'])
						&& substr($_GET['redirect'], 0, 1) === '/') {
							$redirect = $_GET['redirect'];
						}
						header('Location: '.$redirect);
						die('Happy configuring!');
					}
					$switchAuthSuccess = true;
				}
				break;
			}
		}

		// Error message - login not valid
		$infoClass = 'error';
		$info = translate('Login failed', false);
	}

} elseif(isset($_GET['reason']) && $_GET['reason'] == 'unavailable') {

	$infoClass = 'info';
	$info = translate('Maintenance Mode - Please try again later.', false);

} elseif(isset($_GET['reason']) && $_GET['reason'] == 'timeout') {

	$infoClass = 'warn';
	$info = translate('Session timed out. Please log in again.', false);

} elseif(isset($_GET['reason']) && $_GET['reason'] == 'notloggedin') {

	$infoClass = 'warn';
	$info = translate('Please log in first.', false);

} elseif(isset($_GET['reason']) && $_GET['reason'] == 'setup_complete') {

	$infoClass = 'ok';
	$info = translate('Setup complete! Please log in.', false);

} elseif(isWebUserAuthenticated()) {

	// Already signed in via web auth
	header('Location: index.php'); exit();

} elseif(!isDatastoreInitialized() && isset($_SESSION['username']) && isset($_SESSION['password'])) {

	// Legacy: already signed in via switch auth (no web users exist)
	header('Location: index.php'); exit();

}

?>

<!DOCTYPE html>
<html>
<head>
	<title><?php translate('Cisco Switch Manager GUI'); ?> - <?php translate('Log In'); ?></title>
	<?php require('head.inc.php'); ?>
	<script type='text/javascript' src='webdesign-template/js/main.js'></script>
	<script type='text/javascript' src='js/explode.js'></script>
	<style>
	#forkme {
		position: absolute;
		top: 0; right: 0;
	}
	@media only screen and (max-width: 620px) {
		#logincontainer {
			margin-top: 20px;
		}
	}
	</style>
</head>
<body>
	<script>
	function beginFadeOutAnimation() {
		document.getElementById('imgSwitch').style.opacity = 0;
		document.getElementById('imgLoading').style.opacity = 1;
		document.getElementById('submitLogin').disabled = true;
		document.getElementById('username').readOnly = true;
		document.getElementById('password').readOnly = true;
	}
	</script>

	<a id='forkme' href='https://github.com/isolson/cisco-switch-manager-gui'><img src='img/forkme.png'></a>

	<div id='container'>
		<h1 id='title'><div id='logo'></div></h1>

		<div id='splash' class='login'>
			<div id='subtitle'>
				<div id='imgContainer'>
					<img id='imgLoading' src='img/loading.svg'></img>
					<img id='imgSwitch' src='img/switch.login.png' class='easteregg-trigger' onclick='boom()' title='initiate self destruction'></img>
				</div>
				<p class='first'>
					<?php translate('This web application allows you to configure Cisco switches through a graphical interface.'); ?>
				</p>
				<p class='toolbar-margin-top'>
					<b><?php translate('Web Interface Login'); ?></b><br>
					<?php translate('Please log in with your web interface credentials.'); ?>
				</p>
			</div>

			<?php require_once('php/browsercheck.php'); ?>

			<?php if($info != '') { ?>
				<div class='infobox <?php echo $infoClass; ?>'><?php echo $info; ?></div>
			<?php } ?>

			<form method='POST' name='loginform' id='frmLogin' onsubmit='beginFadeOutAnimation();'>
				<div class='form-row icon'>
					<input type='text' id='username' name='username' placeholder='<?php translate('Username'); ?>' autofocus='true' />
					<img src='webdesign-template/img/person.svg'>
				</div>
				<div class='form-row password icon'>
					<input type='password' id='password' name='password' placeholder='<?php translate('Password'); ?>' />
					<img src='webdesign-template/img/key.svg'>
					<img src='webdesign-template/img/eye.svg' class='right showpassword' title='Kennwort anzeigen'>
				</div>
				<div class='form-row'>
					<button id='submitLogin' class='slubbutton'><?php translate('Log In'); ?></button>
				</div>
			</form>

		</div>

		<?php require('foot.inc.php'); ?>

	</div>

</body>
</html>
