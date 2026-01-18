<?php

/**
 * User Operations - User CRUD operations
 *
 * Manages web interface user accounts stored in users.json
 */

require_once(__DIR__ . '/datastore.php');

/**
 * Get a user by username
 *
 * @param string $username Username to find
 * @return array|null User data or null if not found
 */
function getUserByUsername($username) {
	$users = getUsers();

	foreach ($users as $user) {
		if (isset($user['username']) && $user['username'] === $username) {
			return $user;
		}
	}

	return null;
}

/**
 * Get a user by ID
 *
 * @param string $id User ID
 * @return array|null User data or null if not found
 */
function getUserById($id) {
	$users = getUsers();

	foreach ($users as $user) {
		if (isset($user['id']) && $user['id'] === $id) {
			return $user;
		}
	}

	return null;
}

/**
 * Create a new user
 *
 * @param string $username Username
 * @param string $password Password (plaintext)
 * @param string $role User role (admin, user)
 * @return array ['success' => bool, 'error' => string, 'user' => array]
 */
function createUser($username, $password, $role = 'admin') {
	$users = getUsers();

	// Check if username already exists
	if (getUserByUsername($username) !== null) {
		return ['success' => false, 'error' => 'Username already exists.', 'user' => null];
	}

	// Hash password with bcrypt
	$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

	if ($passwordHash === false) {
		return ['success' => false, 'error' => 'Failed to hash password.', 'user' => null];
	}

	$user = [
		'id' => generateId('user_'),
		'username' => $username,
		'password_hash' => $passwordHash,
		'role' => $role,
		'created_at' => date('Y-m-d H:i:s'),
		'updated_at' => date('Y-m-d H:i:s')
	];

	$users[] = $user;

	if (!saveUsers($users)) {
		return ['success' => false, 'error' => 'Failed to save user data.', 'user' => null];
	}

	// Return user without password hash
	unset($user['password_hash']);
	return ['success' => true, 'error' => '', 'user' => $user];
}

/**
 * Update a user
 *
 * @param string $id User ID
 * @param array $updates Fields to update (username, password, role)
 * @return array ['success' => bool, 'error' => string]
 */
function updateUser($id, $updates) {
	$users = getUsers();
	$found = false;

	foreach ($users as $index => $user) {
		if (isset($user['id']) && $user['id'] === $id) {
			$found = true;

			// Update username if provided
			if (isset($updates['username']) && $updates['username'] !== $user['username']) {
				// Check if new username is taken
				if (getUserByUsername($updates['username']) !== null) {
					return ['success' => false, 'error' => 'Username already exists.'];
				}
				$users[$index]['username'] = $updates['username'];
			}

			// Update password if provided
			if (isset($updates['password']) && !empty($updates['password'])) {
				$passwordHash = password_hash($updates['password'], PASSWORD_BCRYPT, ['cost' => 12]);
				if ($passwordHash === false) {
					return ['success' => false, 'error' => 'Failed to hash password.'];
				}
				$users[$index]['password_hash'] = $passwordHash;
			}

			// Update role if provided
			if (isset($updates['role'])) {
				$users[$index]['role'] = $updates['role'];
			}

			$users[$index]['updated_at'] = date('Y-m-d H:i:s');
			break;
		}
	}

	if (!$found) {
		return ['success' => false, 'error' => 'User not found.'];
	}

	if (!saveUsers($users)) {
		return ['success' => false, 'error' => 'Failed to save user data.'];
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Delete a user
 *
 * @param string $id User ID
 * @return array ['success' => bool, 'error' => string]
 */
function deleteUser($id) {
	$users = getUsers();
	$found = false;

	// Don't allow deleting the last admin
	$adminCount = 0;
	$deletingAdmin = false;

	foreach ($users as $user) {
		if (isset($user['role']) && $user['role'] === 'admin') {
			$adminCount++;
		}
		if (isset($user['id']) && $user['id'] === $id) {
			$deletingAdmin = isset($user['role']) && $user['role'] === 'admin';
		}
	}

	if ($deletingAdmin && $adminCount <= 1) {
		return ['success' => false, 'error' => 'Cannot delete the last admin user.'];
	}

	$newUsers = [];
	foreach ($users as $user) {
		if (isset($user['id']) && $user['id'] === $id) {
			$found = true;
			continue;
		}
		$newUsers[] = $user;
	}

	if (!$found) {
		return ['success' => false, 'error' => 'User not found.'];
	}

	if (!saveUsers($newUsers)) {
		return ['success' => false, 'error' => 'Failed to save user data.'];
	}

	return ['success' => true, 'error' => ''];
}

/**
 * Get all users (without password hashes)
 *
 * @return array List of users
 */
function getAllUsers() {
	$users = getUsers();
	$result = [];

	foreach ($users as $user) {
		$result[] = [
			'id' => $user['id'] ?? '',
			'username' => $user['username'] ?? '',
			'role' => $user['role'] ?? 'admin',
			'created_at' => $user['created_at'] ?? null,
			'updated_at' => $user['updated_at'] ?? null
		];
	}

	return $result;
}

/**
 * Change user password
 *
 * @param string $id User ID
 * @param string $currentPassword Current password
 * @param string $newPassword New password
 * @return array ['success' => bool, 'error' => string]
 */
function changeUserPassword($id, $currentPassword, $newPassword) {
	$user = getUserById($id);

	if ($user === null) {
		return ['success' => false, 'error' => 'User not found.'];
	}

	// Verify current password
	if (!password_verify($currentPassword, $user['password_hash'])) {
		return ['success' => false, 'error' => 'Current password is incorrect.'];
	}

	return updateUser($id, ['password' => $newPassword]);
}
