<?php

/**
 * Setup Wizard - First-time setup for creating admin account
 */

require_once('lang.php');
require_once('php/session-options.php');
require_once('php/auth.php');

// If already set up, redirect to login
if (!needsSetup()) {
	header('Location: login.php');
	exit();
}

$info = '';
$infoClass = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';
	$confirmPassword = $_POST['confirm_password'] ?? '';

	// Validate input
	$usernameValidation = validateUsername($username);
	$passwordValidation = validatePassword($password);

	if (!$usernameValidation['valid']) {
		$info = implode(' ', $usernameValidation['errors']);
		$infoClass = 'error';
	} elseif (!$passwordValidation['valid']) {
		$info = implode(' ', $passwordValidation['errors']);
		$infoClass = 'error';
	} elseif ($password !== $confirmPassword) {
		$info = translate('Passwords do not match.', false);
		$infoClass = 'error';
	} else {
		// Create the admin user
		$result = createInitialAdmin($username, $password);

		if ($result['success']) {
			// Auto-login the new admin
			$user = authenticateWebUser($username, $password);
			if ($user) {
				startWebSession($user);
				header('Location: index.php');
				exit();
			} else {
				header('Location: login.php?reason=setup_complete');
				exit();
			}
		} else {
			$info = $result['error'];
			$infoClass = 'error';
		}
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<title><?php translate('Setup'); ?> - <?php translate('Cisco Switch Manager GUI'); ?></title>
	<?php require('head.inc.php'); ?>
	<script type='text/javascript' src='webdesign-template/js/main.js'></script>
	<style>
	.setup-container {
		max-width: 500px;
	}
	.setup-step {
		margin: 20px 0;
		padding: 15px;
		background: #f8f9fa;
		border-radius: 5px;
	}
	.setup-step h3 {
		margin-top: 0;
		color: #333;
	}
	.password-requirements {
		font-size: 0.85em;
		color: #666;
		margin-top: 5px;
	}
	.password-requirements li {
		margin: 3px 0;
	}
	</style>
</head>
<body>
	<div id='container'>
		<h1 id='title'><div id='logo'></div></h1>

		<div id='splash' class='login setup-container'>
			<div id='subtitle'>
				<div id='imgContainer'>
					<img id='imgSwitch' src='img/switch.login.png'></img>
				</div>
				<p class='first'>
					<strong><?php translate('Welcome to Cisco Switch Manager GUI'); ?></strong>
				</p>
				<p>
					<?php translate('Create your administrator account to get started.'); ?>
				</p>
			</div>

			<?php if ($info != '') { ?>
				<div class='infobox <?php echo $infoClass; ?>'><?php echo htmlspecialchars($info); ?></div>
			<?php } ?>

			<div class="setup-step">
				<h3><?php translate('Create Admin Account'); ?></h3>
				<p><?php translate('This account is for logging into the web interface. Switch SSH credentials can be configured later.'); ?></p>

				<form method='POST' name='setupform' id='frmSetup'>
					<div class='form-row icon'>
						<input type='text' id='username' name='username' placeholder='<?php translate('Username'); ?>'
							   value='<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>'
							   autofocus='true' autocomplete='username' required />
						<img src='webdesign-template/img/person.svg'>
					</div>
					<div class='form-row password icon'>
						<input type='password' id='password' name='password' placeholder='<?php translate('Password'); ?>'
							   autocomplete='new-password' required />
						<img src='webdesign-template/img/key.svg'>
						<img src='webdesign-template/img/eye.svg' class='right showpassword' title='<?php translate('Show password'); ?>'>
					</div>
					<div class='form-row password icon'>
						<input type='password' id='confirm_password' name='confirm_password' placeholder='<?php translate('Confirm Password'); ?>'
							   autocomplete='new-password' required />
						<img src='webdesign-template/img/key.svg'>
						<img src='webdesign-template/img/eye.svg' class='right showpassword' title='<?php translate('Show password'); ?>'>
					</div>

					<div class="password-requirements">
						<strong><?php translate('Password Requirements'); ?>:</strong>
						<ul>
							<li><?php translate('At least 8 characters'); ?></li>
							<li><?php translate('At least one letter'); ?></li>
							<li><?php translate('At least one number'); ?></li>
						</ul>
					</div>

					<div class='form-row'>
						<button type='submit' class='slubbutton'><?php translate('Create Account & Continue'); ?></button>
					</div>
				</form>
			</div>

		</div>

		<?php require('foot.inc.php'); ?>

	</div>
</body>
</html>
