<?php

/*** MAINTENANCE MESSAGE ***/
const MAINT    = false;
const MAINT_IP = '194.95.144.21';

if(MAINT == true && $_SERVER['REMOTE_ADDR'] != MAINT_IP) {
	header('Location: login.php?reason=unavailable');
	exit();
}


/*** START SESSION ***/
require_once(__DIR__.'/session-options.php');
require_once(__DIR__.'/auth.php');


/*** AUTH CHECK ***/
// Check for web user authentication (new method)
$webUserAuthenticated = isWebUserAuthenticated();

// Check for legacy switch-only authentication (backward compatibility)
$legacyAuthenticated = isset($_SESSION['username']) && isset($_SESSION['password']);

// Determine if user is authenticated
// - If datastore is initialized (users exist), require web auth
// - If datastore not initialized (no users), allow legacy switch auth
$isAuthenticated = false;

if (isDatastoreInitialized()) {
	// Web users exist, require web auth
	$isAuthenticated = $webUserAuthenticated;
} else {
	// No web users, allow legacy switch auth or web auth
	$isAuthenticated = $webUserAuthenticated || $legacyAuthenticated;
}

if (!$isAuthenticated) {
	// redirect to login page
	if(empty($SUPRESS_NOTLOGGEDIN_MESSAGE)) {
		redirectToLogin('notloggedin');
	} else {
		redirectToLogin();
	}
	exit();
}


/*** TIMEOUT HANDLER ***/
$TIMEOUT_MIN = 15;

// check if session is timed out
if(isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > ($TIMEOUT_MIN * 60))) {
	session_unset();     // unset $_SESSION variable for the run-time
	session_destroy();   // destroy session data in storage

	// redirect to login page
	redirectToLogin('timeout');
} else {
	// update last activity time stamp
	$_SESSION['last_activity'] = time();
}


function redirectToLogin($reason=null) {
	$params = [];
	if($reason) {
		$params['reason'] = $reason;
	}
	if(!empty($_SERVER['REQUEST_URI']) && substr($_SERVER['REQUEST_URI'], 0, 1) === '/') {
		$params['redirect'] = $_SERVER['REQUEST_URI'];
	}
	header('HTTP/1.1 401 Not Authorized');
	header('Location: login.php'.(empty($params) ? '' : '?').http_build_query($params));
	die();
}
