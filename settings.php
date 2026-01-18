<?php

require_once('php/session.php');
require_once('php/functions.php');
require_once('config.php');
require_once('php/switchmanagement.php');
require_once('php/useroperations.php');
require_once('lang.php');

$info = "";
$infoClass = "";
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

// Get current web user
$currentUser = getCurrentWebUser();

?>
<!DOCTYPE html>
<html>
<head>
	<title><?php echo translate('Settings', false); ?> - <?php echo translate('Switch Manager', false); ?></title>
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
	.action-buttons { white-space: nowrap; }
	.action-buttons button { margin-right: 5px; }
	.settings-section { padding: 15px; background: #f8f9fa; border-radius: 5px; margin-bottom: 20px; }
	.settings-section h3 { margin-top: 0; }
	.theme-options { display: flex; gap: 15px; flex-wrap: wrap; }
	.theme-option { display: flex; align-items: center; gap: 8px; padding: 15px 20px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
	.theme-option:hover { border-color: #999; }
	.theme-option.active { border-color: #cc0000; background: #fff5f5; }
	.theme-option input { display: none; }
	.theme-icon { font-size: 24px; }
	@media (max-width: 768px) {
		.data-table { font-size: 0.9em; }
		.data-table th, .data-table td { padding: 8px 5px; }
		.action-buttons { display: flex; flex-direction: column; gap: 5px; }
		.action-buttons button { margin-right: 0; }
	}
	</style>
</head>
<body>
	<div id='container'>
		<h1 id='title'><div id='logo'></div></h1>

		<div id='splash' class='login' style='max-width: 900px;'>
			<div id='subtitle'>
				<div id='imgContainer'>
					<img id='imgSwitch' src='img/switch.png'></img>
				</div>
				<?php echo translate('App preferences and user management.', false); ?>
			</div>

			<div id="infobox-container">
				<?php if ($info != '') { ?>
					<div class='infobox <?php echo $infoClass; ?>'><?php echo $info; ?></div>
				<?php } ?>
			</div>

			<div class="tab-nav">
				<a href="?tab=users" class="<?php echo $activeTab === 'users' ? 'active' : ''; ?>"><?php echo translate('Users', false); ?></a>
				<a href="?tab=appearance" class="<?php echo $activeTab === 'appearance' ? 'active' : ''; ?>"><?php echo translate('Appearance', false); ?></a>
				<a href="?tab=system" class="<?php echo $activeTab === 'system' ? 'active' : ''; ?>"><?php echo translate('System', false); ?></a>
			</div>

			<div class="tab-content">
				<?php if ($activeTab === 'users') { ?>
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

				<?php } elseif ($activeTab === 'appearance') { ?>
					<!-- Appearance Tab -->
					<h2><?php echo translate('Appearance', false); ?></h2>
					<p><?php echo translate('Customize the look and feel of the application.', false); ?></p>

					<div class="settings-section">
						<h3><?php echo translate('Theme', false); ?></h3>
						<p class="hint"><?php echo translate('Choose between light and dark mode, or use your system preference.', false); ?></p>
						<div class="theme-options">
							<label class="theme-option" id="theme-system">
								<input type="radio" name="theme" value="system" checked>
								<span class="theme-icon">&#128187;</span>
								<span><?php echo translate('System', false); ?></span>
							</label>
							<label class="theme-option" id="theme-light">
								<input type="radio" name="theme" value="light">
								<span class="theme-icon">&#9728;</span>
								<span><?php echo translate('Light', false); ?></span>
							</label>
							<label class="theme-option" id="theme-dark">
								<input type="radio" name="theme" value="dark">
								<span class="theme-icon">&#9790;</span>
								<span><?php echo translate('Dark', false); ?></span>
							</label>
						</div>
						<p class="hint" style="margin-top: 15px;"><?php echo translate('Note: Theme preference is saved in your browser.', false); ?></p>
					</div>

				<?php } elseif ($activeTab === 'system') { ?>
					<!-- System Tab -->
					<h2><?php echo translate('System Settings', false); ?></h2>

					<div class="settings-section">
						<h3><?php echo translate('Import from config.php', false); ?></h3>
						<p><?php echo translate('Import switches and credentials from your config.php file into the database.', false); ?></p>
						<div class="btn-row">
							<button class="slubbutton secondary" onclick="importSwitches()"><?php echo translate('Import Switches', false); ?></button>
							<button class="slubbutton secondary" onclick="importCredentials()"><?php echo translate('Import Credentials', false); ?></button>
						</div>
					</div>

					<div class="settings-section">
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

					<div class="settings-section">
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
	const form = document.querySelector('#' + id + ' form');
	if (form) form.reset();
}

window.onclick = function(event) {
	if (event.target.classList.contains('modal')) {
		event.target.style.display = 'none';
	}
}

function showInfo(message, type) {
	const container = document.getElementById('infobox-container');
	container.innerHTML = '<div class="infobox ' + type + '">' + message + '</div>';
}

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
document.querySelector('[onclick="openModal(\'user-modal\')"]')?.addEventListener('click', function() {
	document.getElementById('user-modal-title').textContent = '<?php echo translate('Add User', false); ?>';
	document.getElementById('user-id').value = '';
	document.getElementById('user-password').required = true;
	document.getElementById('user-password-hint').style.display = 'none';
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
		} else {
			showInfo(data.error, 'error');
		}
	});
}

// Theme handling
(function() {
	const savedTheme = localStorage.getItem('theme') || 'system';
	document.querySelectorAll('.theme-option').forEach(opt => {
		opt.classList.remove('active');
		if (opt.querySelector('input').value === savedTheme) {
			opt.classList.add('active');
			opt.querySelector('input').checked = true;
		}
	});

	document.querySelectorAll('.theme-option input').forEach(input => {
		input.addEventListener('change', function() {
			const theme = this.value;
			localStorage.setItem('theme', theme);
			document.querySelectorAll('.theme-option').forEach(opt => opt.classList.remove('active'));
			this.closest('.theme-option').classList.add('active');
			applyTheme(theme);
		});
	});

	function applyTheme(theme) {
		if (theme === 'dark') {
			document.documentElement.setAttribute('data-theme', 'dark');
		} else if (theme === 'light') {
			document.documentElement.setAttribute('data-theme', 'light');
		} else {
			document.documentElement.removeAttribute('data-theme');
		}
	}

	applyTheme(savedTheme);
})();
</script>

</body>
</html>
