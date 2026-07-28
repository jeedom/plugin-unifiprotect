<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
	include_file('desktop', '404', 'php');
	die();
}
?>

<form class="form-horizontal">
	<fieldset>
		<legend>
			<i class="fa fa-list-alt"></i> {{Unifi}}
		</legend>
		<div class="form-group">
			<label class="col-lg-3 control-label">{{Controleur Unifi protect}}</label>
			<div class="col-lg-5">
				<div class="input-group">
					<span class="input-group-addon roundedLeft">https://</span>
					<input type="text" class="configKey form-control" data-l1key="controller_ip" placeholder="{{IP du controleur}}" />
					<span class="input-group-addon">:</span>
					<input type="text" class="configKey form-control" data-l1key="controller_port" placeholder="443" />
					<span class="input-group-addon">/</span>
					<input type="text" class="configKey form-control roundedRight" data-l1key="site_id" placeholder="" />
				</div>
			</div>
		</div>
		<div class="form-group">
			<label class="col-lg-3 control-label">{{Utilisateur Unifi protect}}</label>
			<div class="col-lg-5">
				<input class="configKey form-control" data-l1key="controller_user" placeholder="{{Utilisateur}}" />
			</div>
		</div>
		<div class="form-group">
			<label class="col-lg-3 control-label">{{Mot de passe Unifi protect}}</label>
			<div class="col-lg-5">
				<div class="input-group">
					<input type="text" class="configKey form-control roundedLeft inputPassword" data-l1key="controller_password" placeholder="{{Saisir le mot de passe}}" autocomplete="off" />
					<span class="input-group-btn">
						<a class="btn btn-default form-control bt_showPass roundedRight"><i class="fas fa-eye"></i></a>
					</span>
				</div>
			</div>
		</div>
		<div class="form-group">
			<label class="col-lg-3 control-label">{{Découverte}}</label>
			<div class="col-lg-5">
				<a class="btn btn-default" id="bt_syncUnifiProtect"><i class='fas fa-sync'></i> {{Rechercher les équipements Unifi protect}}</a>
			</div>
		</div>
	</fieldset>
	<fieldset>
		<legend>
			<i class="fa fa-list-alt"></i> {{Paramètres}}
		</legend>
		<div class="form-group">
			<label class="col-lg-3 control-label">{{Fréquence de rafraîchissement}}</label>
			<div class="col-lg-2">
				<div class="input-group">
					<input class="configKey form-control roundedLeft" data-l1key="DeamonSleepTime" placeholder="3" />
					<span class="input-group-addon roundedRight">{{secondes}}</span>
				</div>
			</div>
		</div>
		<div class="form-group">
			<label class="col-lg-3 control-label">{{Ne pas récuperer les évenements}}</label>
			<div class="col-lg-5">
				<input type="checkbox" class="configKey" data-l1key="dontGetEvent" />
			</div>
		</div>
	</fieldset>
</form>

<script>
	var changed = 0;
	$('#bt_syncUnifiProtect').on('click', function() {
		$.ajax({ // fonction permettant de faire de l'ajax
			type: "POST", // methode de transmission des données au fichier php
			url: "plugins/unifiprotect/core/ajax/unifiprotect.ajax.php", // url du fichier php
			data: {
				action: "syncUnifiProtect"
			},
			dataType: 'json',
			error: function(request, status, error) {
				handleAjaxError(request, status, error);
			},
			success: function(data) { // si l'appel a bien fonctionné
				if (data.state != 'ok') {
					$('#div_alert').showAlert({
						message: data.result,
						level: 'danger'
					});
					return;
				}
				$('#div_alert').showAlert({
					message: '{{Synchronisation réussie}}',
					level: 'success'
				});
				changed = 1;
			}
		});
	});
	$('#md_modal').on('dialogclose', function() {
		if (changed == 1) {
			location.reload();
		}
	})
	$('#bt_cronGenerator').on('click', function() {
		jeedom.getCronSelectModal({}, function(result) {
			$('.configKey[data-l1key=unifi_cron]').value(result.value);
		});
	});
</script>