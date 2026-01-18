<?php

/**
 * Settings AJAX Endpoints
 *
 * Handles AJAX requests for the settings page.
 */

require_once('php/session.php');
require_once('config.php');
require_once('php/switchmanagement.php');
require_once('php/useroperations.php');
require_once('php/auth.php');

header('Content-Type: application/json');

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$action = $_GET['action'] ?? '';

	switch ($action) {
		case 'get_switch':
			$addr = $_GET['addr'] ?? '';
			$switch = getSwitchByAddress($addr);
			if ($switch) {
				echo json_encode(['success' => true, 'switch' => $switch]);
			} else {
				echo json_encode(['success' => false, 'error' => 'Switch not found.']);
			}
			break;

		case 'get_credential':
			$id = $_GET['id'] ?? '';
			$credential = getCredentialById($id);
			if ($credential) {
				// Don't send the actual password
				unset($credential['password']);
				echo json_encode(['success' => true, 'credential' => $credential]);
			} else {
				echo json_encode(['success' => false, 'error' => 'Credential not found.']);
			}
			break;

		case 'get_user':
			$id = $_GET['id'] ?? '';
			$user = getUserById($id);
			if ($user) {
				// Remove password hash
				unset($user['password_hash']);
				echo json_encode(['success' => true, 'user' => $user]);
			} else {
				echo json_encode(['success' => false, 'error' => 'User not found.']);
			}
			break;

		case 'get_switches':
			$switches = getMergedSwitches();
			echo json_encode(['success' => true, 'switches' => $switches]);
			break;

		case 'get_credentials':
			$credentials = getAllCredentialsForDisplay();
			echo json_encode(['success' => true, 'credentials' => $credentials]);
			break;

		case 'get_users':
			$users = getAllUsers();
			echo json_encode(['success' => true, 'users' => $users]);
			break;

		default:
			echo json_encode(['success' => false, 'error' => 'Unknown action.']);
	}

	exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$input = json_decode(file_get_contents('php://input'), true);
	$action = $input['action'] ?? '';

	switch ($action) {
		// Switch operations
		case 'create_switch':
			$addr = trim($input['addr'] ?? '');
			$name = trim($input['name'] ?? '');
			$group = trim($input['group'] ?? '');
			$credential = $input['credential'] ?? null;

			if (empty($addr) || empty($name)) {
				echo json_encode(['success' => false, 'error' => 'Address and name are required.']);
				break;
			}

			$result = createSwitch($addr, $name, $group, $credential);
			echo json_encode($result);
			break;

		case 'update_switch':
			$originalAddr = $input['original_addr'] ?? '';
			$updates = [
				'addr' => trim($input['addr'] ?? ''),
				'name' => trim($input['name'] ?? ''),
				'group' => trim($input['group'] ?? ''),
				'credential' => $input['credential'] ?? null
			];

			if (empty($originalAddr)) {
				echo json_encode(['success' => false, 'error' => 'Original address is required.']);
				break;
			}

			$result = updateSwitch($originalAddr, $updates);
			echo json_encode($result);
			break;

		case 'delete_switch':
			$addr = $input['addr'] ?? '';

			if (empty($addr)) {
				echo json_encode(['success' => false, 'error' => 'Address is required.']);
				break;
			}

			$result = deleteSwitch($addr);
			echo json_encode($result);
			break;

		// Credential operations
		case 'create_credential':
			$name = trim($input['name'] ?? '');
			$username = trim($input['username'] ?? '');
			$password = $input['password'] ?? '';

			if (empty($name) || empty($username) || empty($password)) {
				echo json_encode(['success' => false, 'error' => 'Name, username, and password are required.']);
				break;
			}

			$result = createCredential($name, $username, $password);
			echo json_encode($result);
			break;

		case 'update_credential':
			$id = $input['id'] ?? '';
			$updates = [];

			if (isset($input['name'])) {
				$updates['name'] = trim($input['name']);
			}
			if (isset($input['username']) && !empty($input['username'])) {
				$updates['username'] = trim($input['username']);
			}
			if (isset($input['password']) && !empty($input['password'])) {
				$updates['password'] = $input['password'];
			}

			if (empty($id)) {
				echo json_encode(['success' => false, 'error' => 'Credential ID is required.']);
				break;
			}

			$result = updateCredential($id, $updates);
			echo json_encode($result);
			break;

		case 'delete_credential':
			$id = $input['id'] ?? '';

			if (empty($id)) {
				echo json_encode(['success' => false, 'error' => 'Credential ID is required.']);
				break;
			}

			$result = deleteCredential($id);
			echo json_encode($result);
			break;

		// User operations
		case 'create_user':
			$username = trim($input['username'] ?? '');
			$password = $input['password'] ?? '';
			$role = $input['role'] ?? 'admin';

			// Validate username
			$usernameValidation = validateUsername($username);
			if (!$usernameValidation['valid']) {
				echo json_encode(['success' => false, 'error' => implode(' ', $usernameValidation['errors'])]);
				break;
			}

			// Validate password
			$passwordValidation = validatePassword($password);
			if (!$passwordValidation['valid']) {
				echo json_encode(['success' => false, 'error' => implode(' ', $passwordValidation['errors'])]);
				break;
			}

			$result = createUser($username, $password, $role);
			echo json_encode($result);
			break;

		case 'update_user':
			$id = $input['id'] ?? '';
			$updates = [];

			if (isset($input['username']) && !empty($input['username'])) {
				$usernameValidation = validateUsername($input['username']);
				if (!$usernameValidation['valid']) {
					echo json_encode(['success' => false, 'error' => implode(' ', $usernameValidation['errors'])]);
					break;
				}
				$updates['username'] = trim($input['username']);
			}

			if (isset($input['password']) && !empty($input['password'])) {
				$passwordValidation = validatePassword($input['password']);
				if (!$passwordValidation['valid']) {
					echo json_encode(['success' => false, 'error' => implode(' ', $passwordValidation['errors'])]);
					break;
				}
				$updates['password'] = $input['password'];
			}

			if (isset($input['role'])) {
				$updates['role'] = $input['role'];
			}

			if (empty($id)) {
				echo json_encode(['success' => false, 'error' => 'User ID is required.']);
				break;
			}

			$result = updateUser($id, $updates);
			echo json_encode($result);
			break;

		case 'delete_user':
			$id = $input['id'] ?? '';

			if (empty($id)) {
				echo json_encode(['success' => false, 'error' => 'User ID is required.']);
				break;
			}

			// Don't allow deleting yourself
			$currentUser = getCurrentWebUser();
			if ($currentUser && $currentUser['id'] === $id) {
				echo json_encode(['success' => false, 'error' => 'Cannot delete your own account.']);
				break;
			}

			$result = deleteUser($id);
			echo json_encode($result);
			break;

		// Import operations
		case 'import_switches':
			$result = importSwitchesFromConfig();
			echo json_encode($result);
			break;

		case 'import_credentials':
			$result = importCredentialsFromConfig();
			echo json_encode($result);
			break;

		default:
			echo json_encode(['success' => false, 'error' => 'Unknown action.']);
	}

	exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
