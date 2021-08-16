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

if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}
$eqLogics = unifi::byType('unifi');
$ignoreLast_seen = 0;
$ignoreSatisfaction = 0;
$ignoreUptime = 0;
$ifIgnored = "{{Vous avez décidé d'ignorer ces mises à jour dans la configuration du plugin, cette info peut donc ne pas être à jour !}}";
?>

<table class="table table-condensed tablesorter" id="table_healthpiHole">
	<thead>
		<tr>
			<th>{{Type}}</th>
			<th>{{Module}}</th>
			<th>{{ID}}</th>
			<th>{{Etat}}</th>

			<th>{{IP}}</th>
			<th>{{ID Logique}}</th>
			<th>{{Satisfaction}}</th>
			<th>{{UpTime}}</th>
			<th>{{Vu Dernière Fois}}</th>
			<th>{{Mise à jour Dispo}}</th>
			<th>{{Actif}}</th>
			<th>{{Bloqué}}</th>
			<th>{{Présent}}</th>
			<th>{{Date création}}</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ($eqLogics as $eqLogic) {
			$type = $eqLogic->getConfiguration('type');
			if ($type != 'site') continue;
			displayHealthLine($eqLogic, $ignoreLast_seen, $ignoreSatisfaction, $ignoreUptime, $ifIgnored);
			foreach ($eqLogics as $BridgedeqLogic) {
				$sectype = $BridgedeqLogic->getConfiguration('type');
				if ($sectype != 'ugw') continue;
				displayHealthLine($BridgedeqLogic, $ignoreLast_seen, $ignoreSatisfaction, $ignoreUptime, $ifIgnored, '<i class="fas fa-level-up-alt fa-rotate-90"></i>&nbsp;&nbsp;');
			}
			foreach ($eqLogics as $BridgedeqLogic) {
				$sectype = $BridgedeqLogic->getConfiguration('type');
				if ($sectype != 'udm') continue;
				displayHealthLine($BridgedeqLogic, $ignoreLast_seen, $ignoreSatisfaction, $ignoreUptime, $ifIgnored, '<i class="fas fa-level-up-alt fa-rotate-90"></i>&nbsp;&nbsp;');
			}
			foreach ($eqLogics as $BridgedeqLogic) {
				$sectype = $BridgedeqLogic->getConfiguration('type');
				if ($sectype != 'usw') continue;
				displayHealthLine($BridgedeqLogic, $ignoreLast_seen, $ignoreSatisfaction, $ignoreUptime, $ifIgnored, '<i class="fas fa-level-up-alt fa-rotate-90"></i>&nbsp;&nbsp;');
			}
			foreach ($eqLogics as $BridgedeqLogic) {
				$sectype = $BridgedeqLogic->getConfiguration('type');
				if ($sectype != 'uap') continue;
				displayHealthLine($BridgedeqLogic, $ignoreLast_seen, $ignoreSatisfaction, $ignoreUptime, $ifIgnored, '<i class="fas fa-level-up-alt fa-rotate-90"></i>&nbsp;&nbsp;');
			}
			foreach ($eqLogics as $BridgedeqLogic) {
				$sectype = $BridgedeqLogic->getConfiguration('type');
				if ($sectype != 'wlan') continue;
				displayHealthLine($BridgedeqLogic, $ignoreLast_seen, $ignoreSatisfaction, $ignoreUptime, $ifIgnored, '<i class="fas fa-level-up-alt fa-rotate-90"></i>&nbsp;&nbsp;');
			}
			foreach ($eqLogics as $BridgedeqLogic) {
				$sectype = $BridgedeqLogic->getConfiguration('type');
				if ($sectype != 'sta') continue;
				displayHealthLine($BridgedeqLogic, $ignoreLast_seen, $ignoreSatisfaction, $ignoreUptime, $ifIgnored, '<i class="fas fa-level-up-alt fa-rotate-90"></i>&nbsp;&nbsp;');
			}
		}

		function displayHealthLine($eqLogic, $ignoreLast_seen, $ignoreSatisfaction, $ignoreUptime, $ifIgnored, $tab = '') {
			//global $ignoreLast_seen,$ignoreSatisfaction,$ignoreUptime,$ifIgnored;

			$type = $eqLogic->getConfiguration('type');

			echo '<tr>';
			echo '<td><span class="label label-info" style="font-size : 1em;width:100%">' . $type . '</span></td>';
			echo '<td>' . $tab . '<a href="' . $eqLogic->getLinkToConfiguration() . '" style="text-decoration: none;width:100%">' . $eqLogic->getHumanName(true) . '</a>' . ((!$eqLogic->getIsEnable()) ? '&nbsp;<i title="Non actif" class="fas fa-times"></i>' : '') . ((!$eqLogic->getIsvisible()) ? '&nbsp;<i title="Non visible" class="fas fa-eye-slash"></i>' : '') . '</td>';
			echo '<td><span class="label label-info" style="font-size : 1em;width:100%">' . $eqLogic->getId() . '</span></td>';

			$stateNumCmd = $eqLogic->getCmd(null, 'stateNum');
			if (is_object($stateNumCmd)) {
				$stateNum = $stateNumCmd->execCmd();
				if ($eqLogic->getIsEnable() && $stateNum != null) {
					echo '<td><span class="label label-' . (($stateNum == '1') ? 'success' : (($stateNum == '4' || $stateNum == '5') ? 'warning' : 'danger')) . '" style="font-size : 1em;width:100%">' . unifi::getStateTxt($stateNum) . '</span></td>';
				} else {
					echo '<td><span class="label label-danger" style="font-size : 1em;width:100%">{{Inconnu}}</span></td>';
				}
			} else {
				echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
			}

			$ip = $eqLogic->getConfiguration('ip');
			if ($ip != null) {
				echo '<td><span class="label label-info" style="font-size : 1em;width:100%">' . $ip . '</span></td>';
			} else {
				echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
			}
			echo '<td><span class="label label-info" style="font-size : 1em;width:100%">' . $eqLogic->getLogicalId() . '</span></td>';

			$satisfactionCmd = $eqLogic->getCmd(null, 'satisfaction');
			if (is_object($satisfactionCmd)) {
				$satisfaction = $satisfactionCmd->execCmd();
				if ($eqLogic->getIsEnable() && $satisfaction != null) {
					echo '<td><span class="label label-' . (($ignoreSatisfaction == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreSatisfaction == '1') ? ' title="' . $ifIgnored . '"' : '') . '>' . $satisfaction . '%</span></td>';
				} else {
					echo '<td><span class="label label-' . (($ignoreSatisfaction == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreSatisfaction == '1') ? ' title="' . $ifIgnored . '"' : '') . '>{{Inconnu}}</span></td>';
				}
			} else {
				echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
			}

			$uptimeCmd = $eqLogic->getCmd(null, 'system-stats::uptime');
			if (is_object($uptimeCmd)) {
				$uptime = $uptimeCmd->execCmd();
				if ($eqLogic->getIsEnable() && $uptime != null) {
					echo '<td><span class="label label-' . (($ignoreUptime == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreUptime == '1') ? ' title="' . $ifIgnored . '"' : '') . '>' . $uptime . '</span></td>';
				} else {
					echo '<td><span class="label label-' . (($ignoreUptime == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreUptime == '1') ? ' title="' . $ifIgnored . '"' : '') . '>{{Inconnu}}</span></td>';
				}
			} else {
				$uptimeCmd = $eqLogic->getCmd(null, 'uptime');
				if (is_object($uptimeCmd)) {
					$uptime = $uptimeCmd->execCmd();
					if ($eqLogic->getIsEnable() && $uptime != null) {
						echo '<td><span class="label label-' . (($ignoreUptime == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreUptime == '1') ? ' title="' . $ifIgnored . '"' : '') . '>' . $uptime . '</span></td>';
					} else {
						echo '<td><span class="label label-' . (($ignoreUptime == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreUptime == '1') ? ' title="' . $ifIgnored . '"' : '') . '>{{Inconnu}}</span></td>';
					}
				} else if ($type != 'site' && $type != 'wlan') {
					echo '<td><span class="label label-' . (($ignoreUptime == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreUptime == '1') ? ' title="' . $ifIgnored . '"' : '') . '>{{Inconnu}}</span></td>';
				} else {
					echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
				}
			}

			$last_seenCmd = $eqLogic->getCmd(null, 'last_seen');
			if (is_object($last_seenCmd)) {
				$last_seen = $last_seenCmd->execCmd();
				if ($eqLogic->getIsEnable() && $last_seen != null) {
					echo '<td><span class="label label-' . (($ignoreLast_seen == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreLast_seen == '1') ? ' title="' . $ifIgnored . '"' : '') . '>' . $last_seen . '</span></td>';
				} else {
					echo '<td><span class="label label-' . (($ignoreLast_seen == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreLast_seen == '1') ? ' title="' . $ifIgnored . '"' : '') . '>{{Inconnu}}</span></td>';
				}
			} else if ($type != 'site' && $type != 'wlan') {
				echo '<td><span class="label label-' . (($ignoreLast_seen == '1') ? 'danger' : 'info') . '" style="font-size : 1em;width:100%"' . (($ignoreLast_seen == '1') ? ' title="' . $ifIgnored . '"' : '') . '>{{Inconnu}}</span></td>';
			} else {
				echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
			}

			if ($type != 'site') {
				$upgradableCmd = $eqLogic->getCmd(null, 'upgradable');
				if (is_object($upgradableCmd)) {
					$upgradable = $upgradableCmd->execCmd();
					if ($eqLogic->getIsEnable() && $upgradable !== null) {
						echo '<td><span class="label label-' . (($upgradable == '0') ? 'success' : 'warning') . '" style="font-size : 1em;width:100%">' . (($upgradable == '0') ? '{{Non}}' : '{{Oui}}') . '</span></td>';
					} else {
						echo '<td><span class="label label-info" style="font-size : 1em;width:100%">{{Inconnu}}</span></td>';
					}
				} else {
					echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
				}
			} else {
				$controllerHasUpdateCmd = $eqLogic->getCmd(null, 'controllerHasUpdate');
				if (is_object($controllerHasUpdateCmd)) {
					$controllerHasUpdate = $controllerHasUpdateCmd->execCmd();
					if ($eqLogic->getIsEnable() && $controllerHasUpdate !== null) {
						echo '<td><span class="label label-' . (($controllerHasUpdate == '1') ? 'warning' : 'success') . '" style="font-size : 1em;width:100%">' . (($controllerHasUpdate == '1') ? '{{Oui}}' : '{{Non}}') . '</span></td>';
					} else {
						echo '<td><span class="label label-info" style="font-size : 1em;width:100%">{{Inconnu}}</span></td>';
					}
				}
			}
			if ($type == 'wlan') {
				$enabledCmd = $eqLogic->getCmd(null, $eqLogic->getLogicalId() . '::enabled');
				if (is_object($enabledCmd)) {
					$enabled = $enabledCmd->execCmd();
					if ($enabled !== null) {
						echo '<td><span class="label label-' . (($enabled == '0') ? 'danger' : 'success') . '" style="font-size : 1em;width:100%">' . (($enabled == '0') ? '{{Non}}' : '{{Oui}}') . '</span></td>';
					} else {
						echo '<td><span class="label label-info" style="font-size : 1em;width:100%">{{Inconnu}}</span></td>';
					}
				}
			} else if ($type == 'uap') {
				$disabledCmd = $eqLogic->getCmd(null, 'disabled');
				if (is_object($disabledCmd)) {
					$disabled = $disabledCmd->execCmd();
					if ($eqLogic->getIsEnable() && $disabled !== null) {
						echo '<td><span class="label label-' . (($disabled == '1') ? 'danger' : 'success') . '" style="font-size : 1em;width:100%">' . (($disabled == '1') ? '{{Non}}' : '{{Oui}}') . '</span></td>';
					} else {
						echo '<td><span class="label label-info" style="font-size : 1em;width:100%">{{Inconnu}}</span></td>';
					}
				}
			} else {
				echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
			}

			if ($type == 'sta') {
				$blockedCmd = $eqLogic->getCmd(null, 'blocked');
				if (is_object($blockedCmd)) {
					$blocked = $blockedCmd->execCmd();
					if ($eqLogic->getIsEnable() && $blocked !== null) {
						echo '<td><span class="label label-' . (($blocked == '0') ? 'success' : 'danger') . '" style="font-size : 1em;width:100%">' . (($blocked == '0') ? '{{Non}}' : '{{Oui}}') . '</span></td>';
					} else {
						echo '<td><span class="label label-info" style="font-size : 1em;width:100%">{{Inconnu}}</span></td>';
					}
				}
			} else {
				echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
			}

			if ($type == 'sta') {
				$presentCmd = $eqLogic->getCmd(null, 'present');
				if (is_object($presentCmd)) {
					$present = $presentCmd->execCmd();
					if ($eqLogic->getIsEnable() && $present !== null) {
						echo '<td><span class="label label-' . (($present == '0') ? 'danger' : 'success') . '" style="font-size : 1em;width:100%">' . (($present == '0') ? '{{Non}}' : '{{Oui}}') . '</span></td>';
					} else {
						echo '<td><span class="label label-info" style="font-size : 1em;width:100%">{{Inconnu}}</span></td>';
					}
				}
			} else {
				echo '<td><span class="label label-primary" style="font-size : 1em;width:100%" title="{{N\'existe pas pour ce type}}">N/A</span></td>';
			}

			echo '<td><span class="label label-info" style="font-size : 1em;width:100%">' . $eqLogic->getConfiguration('createtime') . '</span></td>';
			echo '</tr>';
		}

		?>
	</tbody>
</table>