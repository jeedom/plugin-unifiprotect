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

/* * ***************************Includes**********************************/
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../3rdparty/unifiprotectapi.class.php';

class unifiprotect extends eqLogic {
	/***************************Attributs*******************************/
	/** @var \unifiprotectapi */
	private static $_unifiprotectController = null;
	public static $_encryptConfigKey = array('controller_api_key');

	public static function cronDaily() {
		self::deamon_start();
		self::sync();
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = '';
		$return['state'] = 'nok';
		$cron = cron::byClassAndFunction('unifiprotect', 'pull');
		if (is_object($cron) && $cron->running()) {
			$return['state'] = 'ok';
		}
		$return['launchable'] = 'ok';
		if (trim(config::byKey('controller_ip', 'unifiprotect', '', true)) === '') {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = __('L’adresse du contrôleur UniFi Protect doit être configurée', __FILE__);
		} elseif (trim(config::byKey('controller_api_key', 'unifiprotect', '', true)) === '') {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = __('La clé API UniFi Protect doit être configurée', __FILE__);
		}
		return $return;
	}

	public static function deamon_start() {
		self::deamon_stop();
		$cron = cron::byClassAndFunction('unifiprotect', 'pull');
		if (!is_object($cron)) {
			$cron = new cron();
		}
		$cron->setClass('unifiprotect');
		$cron->setFunction('pull');
		$cron->setDeamon(1);
		$cron->setDeamonSleepTime(config::byKey('DeamonSleepTime', 'unifiprotect', 3, true));
		$cron->setEnable(1);
		$cron->setSchedule('* * * * *');
		$cron->setTimeout(1440);
		$cron->save();
		$cron->stop();

		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		$cron = cron::byClassAndFunction('unifiprotect', 'pull');
		if (!is_object($cron)) {
			throw new Exception(__('Tache cron introuvable', __FILE__));
		}
		$cron->run();
	}

	public static function deamon_stop() {
		$cron = cron::byClassAndFunction('unifiprotect', 'pull');
		if (!is_object($cron)) {
			throw new Exception(__('Tache cron introuvable', __FILE__));
		}
		self::killController();
		$cron->halt();
	}

	/**
	 * @param bool $_mode
	 * @return void
	 * @throws Exception
	 */
	public static function deamon_changeAutoMode($_mode) {
		$cron = cron::byClassAndFunction('unifiprotect', 'pull');
		if (!is_object($cron)) {
			throw new Exception(__('Tache cron introuvable', __FILE__));
		}
		$cron->setEnable($_mode);
		$cron->save();
	}

	public static function devicesParameters($_device = '') {
		$return = array();
		$files = ls(__DIR__ . '/../config/devices', '*.json', false, array('files', 'quiet'));
		foreach ($files as $file) {
			try {
				$content = is_json(file_get_contents(__DIR__ . '/../config/devices/' . $file), false);
				if ($content != false) {
					$return[str_replace('.json', '', $file)] = $content;
				}
			} catch (Exception $e) {
			}
		}
		if (isset($_device) && $_device != '') {
			if (isset($return[$_device])) {
				return $return[$_device];
			}
			return array();
		}
		return $return;
	}

	public static function getController() {
		$controller = self::login();
		if (!$controller) {
			return false;
		}
		return $controller;
	}

	public static function killController() {
		if (self::$_unifiprotectController !== null) {
			self::$_unifiprotectController->logout();
		}
		self::$_unifiprotectController = null;
	}

	public static function login() {
		$controller_api_key = config::byKey('controller_api_key', 'unifiprotect', '', true);
		$controller_url = 'https://' . config::byKey('controller_ip', 'unifiprotect', '', true) . ':' . config::byKey('controller_port', 'unifiprotect', '443', true);
		if (self::$_unifiprotectController === null) {
			try {
				self::$_unifiprotectController = new unifiprotectapi($controller_api_key, $controller_url);
			} catch (Exception $e) {
				log::add('unifiprotect', 'error', $e->getMessage());
				return false;
			}
		}
		if (is_object(self::$_unifiprotectController)) {
			$login = self::$_unifiprotectController->login();
			if ($login !== true) {
				if (is_int($login)) {
					log::add('unifiprotect', 'warning', "Erreur d'accès à l'API officielle UniFi Protect, vérifiez la clé API (HTTP code: $login): " . self::$_unifiprotectController->get_last_error_message());
				} else {
					log::add('unifiprotect', 'warning', "Erreur d'accès à l'API officielle UniFi Protect: " . self::$_unifiprotectController->get_last_error_message());
				}
				return false;
			}
		} else {
			log::add('unifiprotect', 'error', "Erreur création client vers : " . $controller_url);
			return false;
		}
		return self::$_unifiprotectController;
	}

	public static function sync() {
		$controller = self::getController();
		if ($controller  === false) {
			throw new Exception(__('Impossible de se connecter à l’API officielle UniFi Protect', __FILE__));
		}
		$datas = $controller->get_server_info();
		if (!is_array($datas) || !isset($datas['nvr'], $datas['cameras'], $datas['chimes'])) {
			throw new Exception(__('Réponse invalide de l’API officielle UniFi Protect', __FILE__) . ' : ' . $controller->get_last_error_message());
		}
		log::add('unifiprotect', 'info', "Sync data : " . json_encode($datas));

		$nvr = $datas['nvr'];
		log::add('unifiprotect', 'info', "Find NVR " . $nvr['name'] . " (" . $nvr['id'] . ")");
		$eqLogic = self::findEquipment($nvr, 'nvr');
		if (!is_object($eqLogic)) {
			$eqLogic = new unifiprotect();
			$eqLogic->setName($nvr['name']);
			$eqLogic->setIsEnable(1);
			$eqLogic->setIsVisible(1);
			$eqLogic->setLogicalId('nvr::' . $nvr['id']);
			$eqLogic->setEqType_name('unifiprotect');
		}
		$eqLogic->setConfiguration('type', 'nvr');
		$eqLogic->setConfiguration('isNVR', true);
		$eqLogic->setConfiguration('isCamera', false);
		$eqLogic->setConfiguration('isChime', false);
		$eqLogic->setConfiguration('device_id', $nvr['id']);
		$eqLogic->setConfiguration('model_key', $nvr['modelKey']);
		$eqLogic->save();

		foreach ($datas['cameras'] as $camera) {
			log::add('unifiprotect', 'info', "Find camera " . $camera['name'] . " (" . $camera['mac'] . ")");
			$eqLogic = self::findEquipment($camera, 'camera');
			if (!is_object($eqLogic)) {
				$eqLogic = new unifiprotect();
				$eqLogic->setName($camera['name']);
				$eqLogic->setIsEnable(1);
				$eqLogic->setIsVisible(1);
				$eqLogic->setLogicalId($camera['mac']);
				$eqLogic->setEqType_name('unifiprotect');
			}
			$eqLogic->setConfiguration('type', 'camera');
			$eqLogic->setConfiguration('isNVR', false);
			$eqLogic->setConfiguration('isCamera', true);
			$eqLogic->setConfiguration('isChime', false);
			$eqLogic->setConfiguration('device_id', $camera['id']);
			$eqLogic->setConfiguration('mac', $camera['mac']);
			$eqLogic->setConfiguration('model_key', $camera['modelKey']);
			$eqLogic->save();

			if (class_exists('camera')) {
				$camera_jeedom = eqLogic::byLogicalId($camera['id'], 'camera');
				if (!is_object($camera_jeedom)) {
					$camera_jeedom = new camera();
					$camera_jeedom->setIsEnable(1);
					$camera_jeedom->setIsVisible(1);
					$camera_jeedom->setName($camera['name']);
				}
				$camera_jeedom->setConfiguration('ip', $camera['id']);
				$camera_jeedom->setConfiguration('urlStream', 'unifiprotect::get_snapshot');
				$camera_jeedom->setEqType_name('camera');
				$camera_jeedom->setLogicalId($camera['id']);
				$camera_jeedom->save(true);
			}
		}

		foreach ($datas['chimes'] as $chime) {
			log::add('unifiprotect', 'info', "Find chime " . $chime['name'] . " (" . $chime['mac'] . ")");
			$eqLogic = self::findEquipment($chime, 'chime');
			if (!is_object($eqLogic)) {
				$eqLogic = new unifiprotect();
				$eqLogic->setName($chime['name']);
				$eqLogic->setIsEnable(1);
				$eqLogic->setIsVisible(1);
				$eqLogic->setLogicalId($chime['mac']);
				$eqLogic->setEqType_name('unifiprotect');
			}
			$eqLogic->setConfiguration('type', 'chime');
			$eqLogic->setConfiguration('isNVR', false);
			$eqLogic->setConfiguration('isCamera', false);
			$eqLogic->setConfiguration('isChime', true);
			$eqLogic->setConfiguration('device_id', $chime['id']);
			$eqLogic->setConfiguration('mac', $chime['mac']);
			$eqLogic->setConfiguration('model_key', $chime['modelKey']);
			$eqLogic->save();
		}
		self::pull();
	}

	private static function findEquipment($device, $kind) {
		if (isset($device['mac']) && $device['mac'] !== '') {
			$eqLogic = self::byLogicalId($device['mac'], 'unifiprotect');
			if (is_object($eqLogic)) {
				return $eqLogic;
			}
		}

		foreach (self::byType('unifiprotect') as $eqLogic) {
			if ($eqLogic->getConfiguration('device_id') == $device['id']) {
				return $eqLogic;
			}
			if ($kind === 'nvr' && $eqLogic->getConfiguration('isNVR', false)) {
				return $eqLogic;
			}
		}
		return null;
	}

	public static function pull() {
		/** @var unifiprotect[] $eqLogics */
		$eqLogics = self::byType('unifiprotect', true);
		$controller = self::getController();
		if ($controller  === false) {
			foreach ($eqLogics as $eqLogic) {
				$eqLogic->checkAndUpdateCmd('state', $eqLogic->getConfiguration('isNVR', false) ? 0 : 'DISCONNECTED');
				$eqLogic->checkAndUpdateCmd('isConnected', 0);
			}
			throw new Exception(__('Impossible de se connecter à l’API officielle UniFi Protect', __FILE__));
		}
		$server_info = $controller->get_server_info();
		if (!is_array($server_info) || !isset($server_info['nvr'], $server_info['cameras'], $server_info['chimes'])) {
			foreach ($eqLogics as $eqLogic) {
				$eqLogic->checkAndUpdateCmd('state', $eqLogic->getConfiguration('isNVR', false) ? 0 : 'DISCONNECTED');
				$eqLogic->checkAndUpdateCmd('isConnected', 0);
			}
			throw new Exception(__('Réponse invalide de l’API officielle UniFi Protect', __FILE__) . ' : ' . $controller->get_last_error_message());
		}
		log::add('unifiprotect', 'debug', "Pull data : " . json_encode($server_info));
		foreach ($eqLogics as $eqLogic) {
			$datas = null;
			if ($eqLogic->getConfiguration('isCamera', false)) {
				foreach ($server_info['cameras'] as $camera) {
					if ($camera['id'] == $eqLogic->getConfiguration('device_id')) {
						$datas = $camera;
						break;
					}
				}
			} else if ($eqLogic->getConfiguration('isChime', false)) {
				foreach ($server_info['chimes'] as $chime) {
					if ($chime['id'] == $eqLogic->getConfiguration('device_id')) {
						$datas = $chime;
						break;
					}
				}
			} else {
				$datas = $server_info['nvr'];
			}
			if ($datas == null) {
				$eqLogic->checkAndUpdateCmd('state', 'DISCONNECTED');
				$eqLogic->checkAndUpdateCmd('isConnected', 0);
				continue;
			}
			$eqLogic->update_cmds($datas);
		}
	}

	public function update_cmds($datas) {
		foreach ($this->getCmd('info') as $cmd) {
			$paths = explode('::', $cmd->getLogicalId());
			$value = $datas;
			foreach ($paths as $key) {
				if (!is_array($value) || !array_key_exists($key, $value)) {
					continue 2;
				}
				$value = $value[$key];
			}
			$this->checkAndUpdateCmd($cmd, $value);
		}
	}

	public function get_snapshot(\eqLogic $_eqLogic) {
		$controller = self::getController();
		if (!is_object($controller)) {
			return null;
		}
		$camera_id = $_eqLogic->getEqType_name() == 'camera'
			? $_eqLogic->getConfiguration('ip')
			: $_eqLogic->getConfiguration('device_id');
		$snapshot = $controller->get_snapshot($camera_id);
		if ($snapshot === false) {
			log::add('unifiprotect', 'error', 'Erreur de récupération du snapshot : ' . $controller->get_last_error_message());
			return null;
		}
		return $snapshot;
	}


	/***********************Methode d'instance**************************/

	public function postSave() {
		$this->applyModuleConfiguration();
	}

	public function applyModuleConfiguration() {
		if ($this->getConfiguration('applyType') == $this->getConfiguration('type')) {
			return true;
		}

		$this->setConfiguration('applyType', $this->getConfiguration('type'));
		$this->save(true);

		$this->importConfig(false);
	}

	public function importConfig(bool $_dontRemove = true) {
		if ($this->getConfiguration('type') == '') {
			return true;
		}
		$device = self::devicesParameters($this->getConfiguration('type'));
		if (!is_array($device) || !isset($device['commands'])) {
			return true;
		}
		log::add(__CLASS__, 'info', "Apply configuration for device type " . $this->getConfiguration('type'));
		$this->import($device, $_dontRemove);
	}

	public function getImage() {
		if (method_exists($this, 'getCustomImage')) {
			$customImage = $this->getCustomImage();
			if ($customImage !== null) {
				return $customImage;
			}
		}
		if (file_exists(__DIR__ . '/../config/devices/' .  $this->getConfiguration('type') . '.png')) {
			return 'plugins/unifiprotect/core/config/devices/' .  $this->getConfiguration('type') . '.png';
		}
		return false;
	}
}

class unifiprotectCmd extends cmd {
	/***************************Attributs*******************************/

	/*************************Methode static****************************/

	/***********************Methode d'instance**************************/


	public function execute($_options = null) {
		if ($this->getType() == 'info') {
			return;
		}
		unifiprotect::pull();
	}

	/************************Getteur Setteur****************************/
}
