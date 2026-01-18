<?php

/**
 * Auth - Web user authentication functions
 *
 * Handles authentication for the web interface using bcrypt hashed passwords.
 * This is separate from switch SSH credentials.
 */

require_once(__DIR__ . '/datastore.php');
require_once(__DIR__ . '/useroperations.php');

/**
 * Authenticate a web user
 *
 * @param string $username Username
 * @param string $password Password (plaintext)
 * @return array|false User data (without password) or false on failure
 */
function authenticateWebUser($username, $password) {
	$user = getUserByUsername($username);

	if ($user === null) {
		// Prevent timing attacks
		password_verify($password, '$2y$10$dummy_hash_to_prevent_timing_attacks');
		return false;
	}

	if (!password_verify($password, $user['password_hash'])) {
		return false;
	}

	// Return user data without password hash
	return [
		'id' => $user['id'],
		'username' => $user['username'],
		'role' => $user['role'] ?? 'admin',
		'created_at' => $user['created_at'] ?? null
	];
}

/**
 * Start an authenticated web session
 *
 * @param array $user User data from authenticateWebUser()
 */
function startWebSession($user) {
	$_SESSION['web_user'] = $user;
	$_SESSION['web_user_authenticated'] = true;
	$_SESSION['last_activity'] = time();
}

/**
 * Check if user is authenticated via web login
 *
 * @return bool True if authenticated
 */
function isWebUserAuthenticated() {
	return isset($_SESSION['web_user_authenticated']) && $_SESSION['web_user_authenticated'] === true;
}

/**
 * Get the current web user
 *
 * @return array|null User data or null if not authenticated
 */
function getCurrentWebUser() {
	if (!isWebUserAuthenticated()) {
		return null;
	}
	return $_SESSION['web_user'] ?? null;
}

/**
 * End the web user session
 */
function endWebSession() {
	unset($_SESSION['web_user']);
	unset($_SESSION['web_user_authenticated']);
	// Keep switch credentials in session if they exist
}

/**
 * Check if the system needs initial setup
 *
 * @return bool True if setup is required
 */
function needsSetup() {
	return !isDatastoreInitialized();
}

/**
 * Create the initial admin user during setup
 *
 * @param string $username Admin username
 * @param string $password Admin password
 * @return array ['success' => bool, 'error' => string]
 */
function createInitialAdmin($username, $password) {
	// Verify no users exist yet
	if (isDatastoreInitialized()) {
		return ['success' => false, 'error' => 'Setup has already been completed.'];
	}

	// Validate input
	if (strlen($username) < 3) {
		return ['success' => false, 'error' => 'Username must be at least 3 characters.'];
	}

	if (strlen($password) < 8) {
		return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
	}

	// Create the admin user
	$result = createUser($username, $password, 'admin');

	if (!$result['success']) {
		return $result;
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Validate password strength
 *
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePassword($password) {
	$errors = [];

	if (strlen($password) < 8) {
		$errors[] = 'Password must be at least 8 characters.';
	}

	// At least one letter
	if (!preg_match('/[a-zA-Z]/', $password)) {
		$errors[] = 'Password must contain at least one letter.';
	}

	// At least one number
	if (!preg_match('/[0-9]/', $password)) {
		$errors[] = 'Password must contain at least one number.';
	}

	return [
		'valid' => empty($errors),
		'errors' => $errors
	];
}

/**
 * Validate username
 *
 * @param string $username Username to validate
 * @return array ['valid' => bool, 'errors' => array]
 */
function validateUsername($username) {
	$errors = [];

	if (strlen($username) < 3) {
		$errors[] = 'Username must be at least 3 characters.';
	}

	if (strlen($username) > 50) {
		$errors[] = 'Username must be at most 50 characters.';
	}

	// Only alphanumeric, underscore, hyphen
	if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
		$errors[] = 'Username can only contain letters, numbers, underscores, and hyphens.';
	}

	return [
		'valid' => empty($errors),
		'errors' => $errors
	];
}
