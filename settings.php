<?php

require_once('php/session.php');
require_once('php/functions.php');
require_once('config.php');
require_once('php/switchmanagement.php');
require_once('php/useroperations.php');
require_once('lang.php');

$info = "";
$infoClass = "";
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'switches';

// Get current web user
$currentUser = getCurrentWebUser();

?>
<!DOCTYPE html>
<html>
<head>
	<title><?php echo translate('Settings', false); ?> - <?php echo translate('Cisco Switch Manager GUI', false); ?></title>
	<?php require('head.inc.php'); ?>
	<style>
	.tab-nav { display: flex; gap: 5px; margin-bottom: 20px; flex-wrap: wrap; }
	.tab-nav a { padding: 10px 20px; text-decoration: none; border: 1px solid #ddd; border-bottom: none; border-radius: 5px 5px 0 0; background: #f5f5f5; color: #333; }
	.tab-nav a.active { background: white; border-bottom: 1px solid white; margin-bottom: -1px; }
	.tab-content { border: 1px solid #ddd; padding: 20px; border-radius: 0 5px 5px 5px; background: white; }
	.data-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
	.data-table th, .data-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
	.data-table th { background: #f8f9fa; }
	.data-table tr:hover { background: #f5f5f5; }
	.btn-row { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
	.btn-small { padding: 5px 10px; font-size: 0.9em; }
	input[type="text"], input[type="password"], select { width: 100%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
	label { font-weight: bold; display: block; margin-top: 10px; }
	.hint { font-size: 0.85em; color: #666; margin-top: -10px; margin-bottom: 15px; }
	.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
	.modal-content { background-color: white; margin: 10% auto; padding: 20px; border-radius: 8px; max-width: 500px; position: relative; }
	.modal-close { position: absolute; right: 15px; top: 10px; font-size: 24px; cursor: pointer; color: #666; }
	.modal-close:hover { color: #333; }
	.status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 0.85em; }
	.status-badge.admin { background: #d4edda; color: #155724; }
	.status-badge.user { background: #e9ecef; color: #495057; }
	.status-badge.config { background: #fff3cd; color: #856404; }
	.action-buttons { white-space: nowrap; }
	.action-buttons button, .action-buttons a { margin-right: 5px; }
	.import-section { padding: 15px; background: #f8f9fa; border-radius: 5px; margin-bottom: 20px; }
	.import-section h3 { margin-top: 0; }
	</style>
</head>
<body>
	<div id='container'>
		<h1 id='title'><div id='logo'></div></h1>

		<div id='splash' class='login' style='max-width: 1000px;'>
			<div id='subtitle'>
				<div id='imgContainer'>
					<img id='imgSwitch' src='img/switch.png'></img>
				</div>
				<?php echo translate('Manage switches, credentials, and users.', false); ?>
			</div>

			<div id="infobox-container">
				<?php if ($info != '') { ?>
					<div class='infobox <?php echo $infoClass; ?>'><?php echo $info; ?></div>
				<?php } ?>
			</div>

			<div class="tab-nav">
				<a href="?tab=switches" class="<?php echo $activeTab === 'switches' ? 'active' : ''; ?>"><?php echo translate('Switches', false); ?></a>
				<a href="?tab=credentials" class="<?php echo $activeTab === 'credentials' ? 'active' : ''; ?>"><?php echo translate('Credentials', false); ?></a>
				<a href="?tab=users" class="<?php echo $activeTab === 'users' ? 'active' : ''; ?>"><?php echo translate('Users', false); ?></a>
				<a href="?tab=system" class="<?php echo $activeTab === 'system' ? 'active' : ''; ?>"><?php echo translate('System', false); ?></a>
			</div>

			<div class="tab-content">
				<?php if ($activeTab === 'switches') { ?>
					<!-- Switches Tab -->
					<h2><?php echo translate('Switch Management', false); ?></h2>
					<p><?php echo translate('Add, edit, or remove switches.', false); ?></p>

					<button class="slubbutton" onclick="openModal('switch-modal')"><?php echo translate('Add Switch', false); ?></button>

					<table class="data-table" id="switches-table">
						<thead>
							<tr>
								<th><?php echo translate('Name', false); ?></th>
								<th><?php echo translate('Address', false); ?></th>
								<th><?php echo translate('Group', false); ?></th>
								<th><?php echo translate('Credential', false); ?></th>
								<th><?php echo translate('Actions', false); ?></th>
							</tr>
						</thead>
						<tbody id="switches-tbody">
							<?php
							$switches = getMergedSwitches();
							$credentials = getAllCredentialsForDisplay();
							foreach ($switches as $switch) {
								$credName = '-';
								if (isset($switch['credential'])) {
									foreach ($credentials as $cred) {
										if ($cred['id'] === $switch['credential']) {
											$credName = htmlspecialchars($cred['name']);
											break;
										}
									}
								}
								?>
								<tr data-addr="<?php echo htmlspecialchars($switch['addr']); ?>">
									<td><?php echo htmlspecialchars($switch['name']); ?></td>
									<td><?php echo htmlspecialchars($switch['addr']); ?></td>
									<td><?php echo htmlspecialchars($switch['group'] ?? ''); ?></td>
									<td><?php echo $credName; ?></td>
									<td class="action-buttons">
										<button class="slubbutton secondary btn-small" onclick="editSwitch('<?php echo htmlspecialchars($switch['addr'], ENT_QUOTES); ?>')"><?php echo translate('Edit', false); ?></button>
										<button class="slubbutton destructive btn-small" onclick="deleteSwitch('<?php echo htmlspecialchars($switch['addr'], ENT_QUOTES); ?>')"><?php echo translate('Delete', false); ?></button>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>

				<?php } elseif ($activeTab === 'credentials') { ?>
					<!-- Credentials Tab -->
					<h2><?php echo translate('Credential Management', false); ?></h2>
					<p><?php echo translate('Manage SSH credentials for switch access. Credentials are encrypted at rest.', false); ?></p>

					<button class="slubbutton" onclick="openModal('credential-modal')"><?php echo translate('Add Credential', false); ?></button>

					<table class="data-table" id="credentials-table">
						<thead>
							<tr>
								<th><?php echo translate('Name', false); ?></th>
								<th><?php echo translate('Username', false); ?></th>
								<th><?php echo translate('Password', false); ?></th>
								<th><?php echo translate('Source', false); ?></th>
								<th><?php echo translate('Actions', false); ?></th>
							</tr>
						</thead>
						<tbody id="credentials-tbody">
							<?php
							$credentials = getAllCredentialsForDisplay();
							foreach ($credentials as $cred) {
								$fromConfig = isset($cred['from_config']) && $cred['from_config'];
								?>
								<tr data-id="<?php echo htmlspecialchars($cred['id']); ?>">
									<td><?php echo htmlspecialchars($cred['name']); ?></td>
									<td><?php echo htmlspecialchars($cred['username']); ?></td>
									<td><?php echo $cred['password_masked']; ?></td>
									<td>
										<?php if ($fromConfig) { ?>
											<span class="status-badge config"><?php echo translate('config.php', false); ?></span>
										<?php } else { ?>
											<span class="status-badge admin"><?php echo translate('Stored', false); ?></span>
										<?php } ?>
									</td>
									<td class="action-buttons">
										<?php if (!$fromConfig) { ?>
											<button class="slubbutton secondary btn-small" onclick="editCredential('<?php echo htmlspecialchars($cred['id'], ENT_QUOTES); ?>')"><?php echo translate('Edit', false); ?></button>
											<button class="slubbutton destructive btn-small" onclick="deleteCredential('<?php echo htmlspecialchars($cred['id'], ENT_QUOTES); ?>')"><?php echo translate('Delete', false); ?></button>
										<?php } else { ?>
											<span style="color: #666; font-size: 0.9em;"><?php echo translate('Read-only', false); ?></span>
										<?php } ?>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>

				<?php } elseif ($activeTab === 'users') { ?>
					<!-- Users Tab -->
					<h2><?php echo translate('User Management', false); ?></h2>
					<p><?php echo translate('Manage web interface user accounts.', false); ?></p>

					<button class="slubbutton" onclick="openModal('user-modal')"><?php echo translate('Add User', false); ?></button>

					<table class="data-table" id="users-table">
						<thead>
							<tr>
								<th><?php echo translate('Username', false); ?></th>
								<th><?php echo translate('Role', false); ?></th>
								<th><?php echo translate('Created', false); ?></th>
								<th><?php echo translate('Actions', false); ?></th>
							</tr>
						</thead>
						<tbody id="users-tbody">
							<?php
							$users = getAllUsers();
							foreach ($users as $user) {
								$isCurrentUser = $currentUser && $user['id'] === $currentUser['id'];
								?>
								<tr data-id="<?php echo htmlspecialchars($user['id']); ?>">
									<td>
										<?php echo htmlspecialchars($user['username']); ?>
										<?php if ($isCurrentUser) { ?>
											<span style="color: #666; font-size: 0.9em;">(<?php echo translate('you', false); ?>)</span>
										<?php } ?>
									</td>
									<td>
										<span class="status-badge <?php echo $user['role']; ?>">
											<?php echo htmlspecialchars(ucfirst($user['role'])); ?>
										</span>
									</td>
									<td><?php echo htmlspecialchars($user['created_at'] ?? '-'); ?></td>
									<td class="action-buttons">
										<button class="slubbutton secondary btn-small" onclick="editUser('<?php echo htmlspecialchars($user['id'], ENT_QUOTES); ?>')"><?php echo translate('Edit', false); ?></button>
										<?php if (!$isCurrentUser) { ?>
											<button class="slubbutton destructive btn-small" onclick="deleteUser('<?php echo htmlspecialchars($user['id'], ENT_QUOTES); ?>')"><?php echo translate('Delete', false); ?></button>
										<?php } ?>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>

				<?php } elseif ($activeTab === 'system') { ?>
					<!-- System Tab -->
					<h2><?php echo translate('System Settings', false); ?></h2>

					<div class="import-section">
						<h3><?php echo translate('Import from config.php', false); ?></h3>
						<p><?php echo translate('Import switches and credentials from your config.php file into the database.', false); ?></p>
						<div class="btn-row">
							<button class="slubbutton secondary" onclick="importSwitches()"><?php echo translate('Import Switches', false); ?></button>
							<button class="slubbutton secondary" onclick="importCredentials()"><?php echo translate('Import Credentials', false); ?></button>
						</div>
					</div>

					<div class="import-section">
						<h3><?php echo translate('Encryption Status', false); ?></h3>
						<?php
						require_once('php/encryption.php');
						$encryptionWorking = testEncryption();
						?>
						<?php if ($encryptionWorking) { ?>
							<p style="color: #155724;"><strong><?php echo translate('Encryption is working correctly.', false); ?></strong></p>
						<?php } else { ?>
							<p style="color: #721c24;"><strong><?php echo translate('Encryption is not working. Check file permissions on the data directory.', false); ?></strong></p>
						<?php } ?>
						<p class="hint"><?php echo translate('Credentials are encrypted using AES-256-GCM.', false); ?></p>
					</div>

					<div class="import-section">
						<h3><?php echo translate('Data Storage', false); ?></h3>
						<p><?php echo translate('Data directory', false); ?>: <code><?php echo DATA_DIR; ?></code></p>
						<?php if (is_writable(DATA_DIR)) { ?>
							<p style="color: #155724;"><?php echo translate('Directory is writable.', false); ?></p>
						<?php } elseif (!file_exists(DATA_DIR)) { ?>
							<p style="color: #856404;"><?php echo translate('Directory does not exist yet. It will be created automatically.', false); ?></p>
						<?php } else { ?>
							<p style="color: #721c24;"><?php echo translate('Directory is not writable. Check permissions.', false); ?></p>
						<?php } ?>
					</div>

				<?php } ?>
			</div>

			<div style="margin-top: 20px;">
				<a href='index.php'>&gt; <?php echo translate('Back', false); ?></a>
			</div>
		</div>

		<?php require('foot.inc.php'); ?>
	</div>

	<!-- Switch Modal -->
	<div id="switch-modal" class="modal">
		<div class="modal-content">
			<span class="modal-close" onclick="closeModal('switch-modal')">&times;</span>
			<h2 id="switch-modal-title"><?php echo translate('Add Switch', false); ?></h2>
			<form id="switch-form">
				<input type="hidden" id="switch-original-addr" name="original_addr">

				<label for="switch-name"><?php echo translate('Name', false); ?></label>
				<input type="text" id="switch-name" name="name" required placeholder="<?php echo translate('Switch Display Name', false); ?>">

				<label for="switch-addr"><?php echo translate('Address', false); ?></label>
				<input type="text" id="switch-addr" name="addr" required placeholder="<?php echo translate('IP or Hostname', false); ?>">

				<label for="switch-group"><?php echo translate('Group', false); ?></label>
				<input type="text" id="switch-group" name="group" placeholder="<?php echo translate('Group Name', false); ?>" list="switch-groups">
				<datalist id="switch-groups">
					<?php foreach (getSwitchGroups() as $group) { ?>
						<option value="<?php echo htmlspecialchars($group); ?>">
					<?php } ?>
				</datalist>

				<label for="switch-credential"><?php echo translate('Credential', false); ?></label>
				<select id="switch-credential" name="credential">
					<option value=""><?php echo translate('None (use session)', false); ?></option>
					<?php foreach (getAllCredentialsForDisplay() as $cred) { ?>
						<option value="<?php echo htmlspecialchars($cred['id']); ?>"><?php echo htmlspecialchars($cred['name']); ?></option>
					<?php } ?>
				</select>

				<div class="btn-row">
					<button type="submit" class="slubbutton"><?php echo translate('Save', false); ?></button>
					<button type="button" class="slubbutton secondary" onclick="closeModal('switch-modal')"><?php echo translate('Cancel', false); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- Credential Modal -->
	<div id="credential-modal" class="modal">
		<div class="modal-content">
			<span class="modal-close" onclick="closeModal('credential-modal')">&times;</span>
			<h2 id="credential-modal-title"><?php echo translate('Add Credential', false); ?></h2>
			<form id="credential-form">
				<input type="hidden" id="credential-id" name="id">

				<label for="credential-name"><?php echo translate('Name', false); ?></label>
				<input type="text" id="credential-name" name="name" required placeholder="<?php echo translate('Credential Name', false); ?>">

				<label for="credential-username"><?php echo translate('SSH Username', false); ?></label>
				<input type="text" id="credential-username" name="username" required placeholder="<?php echo translate('Username', false); ?>" autocomplete="off">

				<label for="credential-password"><?php echo translate('SSH Password', false); ?></label>
				<input type="password" id="credential-password" name="password" placeholder="<?php echo translate('Password', false); ?>" autocomplete="new-password">
				<p class="hint" id="credential-password-hint"><?php echo translate('Leave blank to keep existing password when editing.', false); ?></p>

				<div class="btn-row">
					<button type="submit" class="slubbutton"><?php echo translate('Save', false); ?></button>
					<button type="button" class="slubbutton secondary" onclick="closeModal('credential-modal')"><?php echo translate('Cancel', false); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- User Modal -->
	<div id="user-modal" class="modal">
		<div class="modal-content">
			<span class="modal-close" onclick="closeModal('user-modal')">&times;</span>
			<h2 id="user-modal-title"><?php echo translate('Add User', false); ?></h2>
			<form id="user-form">
				<input type="hidden" id="user-id" name="id">

				<label for="user-username"><?php echo translate('Username', false); ?></label>
				<input type="text" id="user-username" name="username" required placeholder="<?php echo translate('Username', false); ?>" autocomplete="off">

				<label for="user-password"><?php echo translate('Password', false); ?></label>
				<input type="password" id="user-password" name="password" placeholder="<?php echo translate('Password', false); ?>" autocomplete="new-password">
				<p class="hint" id="user-password-hint"><?php echo translate('Leave blank to keep existing password when editing.', false); ?></p>

				<label for="user-role"><?php echo translate('Role', false); ?></label>
				<select id="user-role" name="role">
					<option value="admin"><?php echo translate('Admin', false); ?></option>
				</select>

				<div class="btn-row">
					<button type="submit" class="slubbutton"><?php echo translate('Save', false); ?></button>
					<button type="button" class="slubbutton secondary" onclick="closeModal('user-modal')"><?php echo translate('Cancel', false); ?></button>
				</div>
			</form>
		</div>
	</div>

<?php require('menu.inc.php'); ?>

<script>
// Modal functions
function openModal(id) {
	document.getElementById(id).style.display = 'block';
}

function closeModal(id) {
	document.getElementById(id).style.display = 'none';
	// Reset forms
	const form = document.querySelector('#' + id + ' form');
	if (form) form.reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
	if (event.target.classList.contains('modal')) {
		event.target.style.display = 'none';
	}
}

function showInfo(message, type) {
	const container = document.getElementById('infobox-container');
	container.innerHTML = '<div class="infobox ' + type + '">' + message + '</div>';
}

// Switch functions
function editSwitch(addr) {
	fetch('settings-ajax.php?action=get_switch&addr=' + encodeURIComponent(addr))
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				document.getElementById('switch-modal-title').textContent = '<?php echo translate('Edit Switch', false); ?>';
				document.getElementById('switch-original-addr').value = data.switch.addr;
				document.getElementById('switch-name').value = data.switch.name;
				document.getElementById('switch-addr').value = data.switch.addr;
				document.getElementById('switch-group').value = data.switch.group || '';
				document.getElementById('switch-credential').value = data.switch.credential || '';
				openModal('switch-modal');
			} else {
				showInfo(data.error, 'error');
			}
		});
}

function deleteSwitch(addr) {
	if (!confirm('<?php echo translate('Are you sure you want to delete this switch?', false); ?>')) return;

	fetch('settings-ajax.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ action: 'delete_switch', addr: addr })
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			location.reload();
		} else {
			showInfo(data.error, 'error');
		}
	});
}

document.getElementById('switch-form').addEventListener('submit', function(e) {
	e.preventDefault();
	const formData = new FormData(this);
	const data = {
		action: formData.get('original_addr') ? 'update_switch' : 'create_switch',
		original_addr: formData.get('original_addr'),
		addr: formData.get('addr'),
		name: formData.get('name'),
		group: formData.get('group'),
		credential: formData.get('credential')
	};

	fetch('settings-ajax.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(data)
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			location.reload();
		} else {
			showInfo(data.error, 'error');
		}
	});
});

// Credential functions
function editCredential(id) {
	fetch('settings-ajax.php?action=get_credential&id=' + encodeURIComponent(id))
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				document.getElementById('credential-modal-title').textContent = '<?php echo translate('Edit Credential', false); ?>';
				document.getElementById('credential-id').value = data.credential.id;
				document.getElementById('credential-name').value = data.credential.name;
				document.getElementById('credential-username').value = data.credential.username;
				document.getElementById('credential-password').value = '';
				document.getElementById('credential-password').required = false;
				document.getElementById('credential-password-hint').style.display = 'block';
				openModal('credential-modal');
			} else {
				showInfo(data.error, 'error');
			}
		});
}

function deleteCredential(id) {
	if (!confirm('<?php echo translate('Are you sure you want to delete this credential?', false); ?>')) return;

	fetch('settings-ajax.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ action: 'delete_credential', id: id })
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			location.reload();
		} else {
			showInfo(data.error, 'error');
		}
	});
}

document.getElementById('credential-form').addEventListener('submit', function(e) {
	e.preventDefault();
	const formData = new FormData(this);
	const data = {
		action: formData.get('id') ? 'update_credential' : 'create_credential',
		id: formData.get('id'),
		name: formData.get('name'),
		username: formData.get('username'),
		password: formData.get('password')
	};

	fetch('settings-ajax.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(data)
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			location.reload();
		} else {
			showInfo(data.error, 'error');
		}
	});
});

// Reset credential form when opening for new
document.querySelector('[onclick="openModal(\'credential-modal\')"]').addEventListener('click', function() {
	document.getElementById('credential-modal-title').textContent = '<?php echo translate('Add Credential', false); ?>';
	document.getElementById('credential-id').value = '';
	document.getElementById('credential-password').required = true;
	document.getElementById('credential-password-hint').style.display = 'none';
});

// User functions
function editUser(id) {
	fetch('settings-ajax.php?action=get_user&id=' + encodeURIComponent(id))
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				document.getElementById('user-modal-title').textContent = '<?php echo translate('Edit User', false); ?>';
				document.getElementById('user-id').value = data.user.id;
				document.getElementById('user-username').value = data.user.username;
				document.getElementById('user-password').value = '';
				document.getElementById('user-password').required = false;
				document.getElementById('user-password-hint').style.display = 'block';
				document.getElementById('user-role').value = data.user.role;
				openModal('user-modal');
			} else {
				showInfo(data.error, 'error');
			}
		});
}

function deleteUser(id) {
	if (!confirm('<?php echo translate('Are you sure you want to delete this user?', false); ?>')) return;

	fetch('settings-ajax.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ action: 'delete_user', id: id })
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			location.reload();
		} else {
			showInfo(data.error, 'error');
		}
	});
}

document.getElementById('user-form').addEventListener('submit', function(e) {
	e.preventDefault();
	const formData = new FormData(this);
	const data = {
		action: formData.get('id') ? 'update_user' : 'create_user',
		id: formData.get('id'),
		username: formData.get('username'),
		password: formData.get('password'),
		role: formData.get('role')
	};

	fetch('settings-ajax.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(data)
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			location.reload();
		} else {
			showInfo(data.error, 'error');
		}
	});
});

// Reset user form when opening for new
document.querySelector('[onclick="openModal(\'user-modal\')"]').addEventListener('click', function() {
	document.getElementById('user-modal-title').textContent = '<?php echo translate('Add User', false); ?>';
	document.getElementById('user-id').value = '';
	document.getElementById('user-password').required = true;
	document.getElementById('user-password-hint').style.display = 'none';
});

// Reset switch form when opening for new
document.querySelector('[onclick="openModal(\'switch-modal\')"]').addEventListener('click', function() {
	document.getElementById('switch-modal-title').textContent = '<?php echo translate('Add Switch', false); ?>';
	document.getElementById('switch-original-addr').value = '';
});

// Import functions
function importSwitches() {
	if (!confirm('<?php echo translate('Import switches from config.php? This will copy switches to the database.', false); ?>')) return;

	fetch('settings-ajax.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ action: 'import_switches' })
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			showInfo('<?php echo translate('Imported', false); ?> ' + data.count + ' <?php echo translate('switches.', false); ?>', 'ok');
			setTimeout(() => location.reload(), 1500);
		} else {
			showInfo(data.error, 'error');
		}
	});
}

function importCredentials() {
	if (!confirm('<?php echo translate('Import credentials from config.php? Credentials will be encrypted and stored securely.', false); ?>')) return;

	fetch('settings-ajax.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ action: 'import_credentials' })
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			showInfo('<?php echo translate('Imported', false); ?> ' + data.count + ' <?php echo translate('credentials.', false); ?>', 'ok');
			setTimeout(() => location.reload(), 1500);
		} else {
			showInfo(data.error, 'error');
		}
	});
}
</script>

</body>
</html>
