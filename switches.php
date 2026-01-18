<?php

require_once('php/session.php');
require_once('php/functions.php');
require_once('config.php');
require_once('php/switchmanagement.php');
require_once('lang.php');

$info = "";
$infoClass = "";
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'switches';

?>
<!DOCTYPE html>
<html>
<head>
	<title><?php echo translate('Switches', false); ?> - <?php echo translate('Switch Manager', false); ?></title>
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
	.status-badge.config { background: #fff3cd; color: #856404; }
	.action-buttons { white-space: nowrap; }
	.action-buttons button { margin-right: 5px; }
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

		<div id='splash' class='login' style='max-width: 1000px;'>
			<div id='subtitle'>
				<div id='imgContainer'>
					<img id='imgSwitch' src='img/switch.png'></img>
				</div>
				<?php echo translate('Manage switches and credentials.', false); ?>
			</div>

			<div id="infobox-container">
				<?php if ($info != '') { ?>
					<div class='infobox <?php echo $infoClass; ?>'><?php echo $info; ?></div>
				<?php } ?>
			</div>

			<div class="tab-nav">
				<a href="?tab=switches" class="<?php echo $activeTab === 'switches' ? 'active' : ''; ?>"><?php echo translate('Switches', false); ?></a>
				<a href="?tab=credentials" class="<?php echo $activeTab === 'credentials' ? 'active' : ''; ?>"><?php echo translate('Credentials', false); ?></a>
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

// Reset forms when opening for new
document.querySelector('[onclick="openModal(\'switch-modal\')"]')?.addEventListener('click', function() {
	document.getElementById('switch-modal-title').textContent = '<?php echo translate('Add Switch', false); ?>';
	document.getElementById('switch-original-addr').value = '';
});

document.querySelector('[onclick="openModal(\'credential-modal\')"]')?.addEventListener('click', function() {
	document.getElementById('credential-modal-title').textContent = '<?php echo translate('Add Credential', false); ?>';
	document.getElementById('credential-id').value = '';
	document.getElementById('credential-password').required = true;
	document.getElementById('credential-password-hint').style.display = 'none';
});
</script>

</body>
</html>
