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
	private static $_unifiprotectController = null;
	public static $_encryptConfigKey = array('controller_ip', 'controller_user', 'controller_password');

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
			self::$_unifiprotectController = self::logout();
		}
		self::$_unifiprotectController = null;
	}

	public static function login() {
		$controller_user = config::byKey('controller_user', 'unifiprotect', '', true);
		$controller_password = config::byKey('controller_password', 'unifiprotect', '', true);
		$controller_url = 'https://' . config::byKey('controller_ip', 'unifiprotect', '', true) . ':' . config::byKey('controller_port', 'unifiprotect', '8443', true);
		$site_id = config::byKey('site_id', 'unifiprotect', 'default', true);
		if ($site_id == '') {
			$site_id = 'default';
		}
		if (self::$_unifiprotectController === null) {
			self::$_unifiprotectController = new unifiprotectapi($controller_user, $controller_password, $controller_url, $site_id);
		}
		if (is_object(self::$_unifiprotectController)) {
			$login = self::$_unifiprotectController->login();
			if ($login !== true) {
				log::add('unifiprotect', 'warning', "Erreur d'accès à Unifi Protect, Vérifiez qu'il répond ou le nom d'utilisateur et mot de passe (" . $login . ') : ' . self::$_unifiprotectController->get_last_error_message());
				return false;
			}
		} else {
			log::add('unifiprotect', 'error', "Error Création client vers : " . $controller_url);
			return false;
		}
		return self::$_unifiprotectController;
	}

	public static function logout() {
		if (self::$_unifiprotectController !== null) {
			self::$_unifiprotectController->logout();
		}
	}

	public static function sync() {
		$controller = self::getController();
		if ($controller  === false) {
			throw new Exception(__('Impossible de se connecter sur Unifi protect', __FILE__));
		}
		$datas = $controller->get_server_info();
		if (!is_array($datas) || !isset($datas['nvr'])) {
			sleep(1);
			$datas = $controller->get_server_info();
		}
		if (!is_array($datas) || !isset($datas['nvr'])) {
			sleep(5);
			$datas = $controller->get_server_info();
		}
		if (!is_array($datas) || !isset($datas['nvr'])) {
			throw new Exception(__('Erreur sur la recuperation des informations de Unifi Protect', __FILE__) . ' => ' . json_encode($datas));
		}
		log::add('unifiprotect', 'info', "Sync data : " . json_encode($datas));
		log::add('unifiprotect', 'info', "Find NVR " . $datas['nvr']['name'] . "(" . $datas['nvr']['mac'] . ")(" . $datas['nvr']['type'] . "):" . json_encode($datas['nvr']));
		$eqLogic = self::byLogicalId($datas['nvr']['mac'], 'unifiprotect');
		if (!is_object($eqLogic)) {
			$eqLogic = new unifiprotect();
			$eqLogic->setName($datas['nvr']['name']);
			$eqLogic->setIsEnable(1);
			$eqLogic->setIsVisible(1);
			$eqLogic->setLogicalId($datas['nvr']['mac']);
			$eqLogic->setEqType_name('unifiprotect');
		}
		$eqLogic->setConfiguration('type', $datas['nvr']['type']);
		$eqLogic->setConfiguration('isNVR', true);
		$eqLogic->setConfiguration('serial', $datas['nvr']['hardwareId']);
		$eqLogic->setConfiguration('device_id', $datas['nvr']['id']);
		$eqLogic->setConfiguration('mac', $datas['nvr']['mac']);
		$eqLogic->setConfiguration('ip', $datas['nvr']['host']);
		$eqLogic->setConfiguration('hardware', $datas['nvr']['version']);
		$eqLogic->setConfiguration('firmware', $datas['nvr']['firmwareVersion']);
		$eqLogic->save();
		foreach ($datas['cameras'] as $camera) {
			log::add('unifiprotect', 'info', "Find camera " . $camera['name'] . "(" . $camera['mac'] . ")(" . $camera['type'] . "):" . json_encode($camera));
			$eqLogic = self::byLogicalId($camera['mac'], 'unifiprotect');
			if (!is_object($eqLogic)) {
				$eqLogic = new unifiprotect();
				$eqLogic->setName($camera['name']);
				$eqLogic->setIsEnable(1);
				$eqLogic->setIsVisible(1);
				$eqLogic->setLogicalId($camera['mac']);
				$eqLogic->setEqType_name('unifiprotect');
			}
			$eqLogic->setConfiguration('type', $camera['type']);
			$eqLogic->setConfiguration('isCamera', true);
			$eqLogic->setConfiguration('device_id', $camera['id']);
			$eqLogic->setConfiguration('mac', $camera['mac']);
			$eqLogic->setConfiguration('ip', $camera['host']);
			$eqLogic->setConfiguration('hardware', $camera['hardwareRevision']);
			$eqLogic->setConfiguration('firmware', $camera['firmwareVersion']);
			$eqLogic->save();
			if (class_exists('camera')) {
				$camera_jeedom = eqLogic::byLogicalId($camera['id'], 'camera');
				if (!is_object($camera_jeedom)) {
					$camera_jeedom = new camera();
					$camera_jeedom->setIsEnable(1);
					$camera_jeedom->setIsVisible(1);
					$camera_jeedom->setName($camera['name']);
				}
				$type = $camera['type'];
				if ($type == 'UVC G4 Bullet') {
					$type = 'g4_bullet';
				}
				$camera_jeedom->setConfiguration('device', 'ubiquiti.' . $type);
				$camera_jeedom->setConfiguration('ip', $camera['id']);
				$camera_jeedom->setConfiguration('urlStream', 'unifiprotect::get_snapshot');
				$camera_jeedom->setEqType_name('camera');
				$camera_jeedom->setLogicalId($camera['id']);
				$camera_jeedom->save();
				$camera_jeedom->setConfiguration('urlStream', 'unifiprotect::get_snapshot');
				$camera_jeedom->save(true);
			}
		}
		foreach ($datas['chimes'] as $chime) {
			log::add('unifiprotect', 'info', "Find chime " . $chime['name'] . "(" . $chime['mac'] . ")(" . $chime['type'] . "):" . json_encode($chime));
			$eqLogic = self::byLogicalId($chime['mac'], 'unifiprotect');
			if (!is_object($eqLogic)) {
				$eqLogic = new unifiprotect();
				$eqLogic->setName($chime['name']);
				$eqLogic->setIsEnable(1);
				$eqLogic->setIsVisible(1);
				$eqLogic->setLogicalId($chime['mac']);
				$eqLogic->setEqType_name('unifiprotect');
			}
			$eqLogic->setConfiguration('type', $chime['type']);
			$eqLogic->setConfiguration('isChime', true);
			$eqLogic->setConfiguration('device_id', $chime['id']);
			$eqLogic->setConfiguration('mac', $chime['mac']);
			$eqLogic->setConfiguration('ip', $chime['host']);
			$eqLogic->setConfiguration('firmware', $chime['firmwareVersion']);
			$eqLogic->save();
		}
		self::pull();
	}

	public static function secondsToTime($ss) {
		$s = $ss % 60;
		$m = floor(($ss % 3600) / 60);
		$h = floor(($ss % 86400) / 3600);
		$d = floor(($ss) / 86400);
		if ($d)
			return sprintf("%d.%02d:%02d:%02d", $d, $h, $m, $s);
		else
			return sprintf("%02d:%02d:%02d", $h, $m, $s);
	}

	public static function pull() {
		$eqLogics = self::byType('unifiprotect', true);
		$controller = self::getController();
		if ($controller  === false) {
			foreach ($eqLogics as $eqLogic) {
				$eqLogic->checkAndUpdateCmd('state', 0);
			}
			throw new Exception(__('Impossible de se connecter sur Unifi protect', __FILE__));
		}
		$server_info = $controller->get_server_info();
		if (!is_array($server_info) || !isset($server_info['nvr'])) {
			foreach ($eqLogics as $eqLogic) {
				$eqLogic->checkAndUpdateCmd('state', 0);
			}
			throw new Exception(__('Erreur sur la recuperation des informations de Unifi Protect', __FILE__) . ' => ' . json_encode($server_info));
		}
		foreach ($eqLogics as $eqLogic) {
			$datas = null;
			if ($eqLogic->getConfiguration('isCamera', false)) {
				foreach ($server_info['cameras'] as $camera) {
					if ($camera['mac'] == $eqLogic->getLogicalId()) {
						$datas = $camera;
					}
				}
			} else if ($eqLogic->getConfiguration('isChime', false)) {
				foreach ($server_info['chimes'] as $chime) {
					if ($chime['mac'] == $eqLogic->getLogicalId()) {
						$datas = $chime;
					}
				}
			} else {
				$datas = $server_info;
			}
			if ($datas == null) {
				continue;
			}
			foreach ($eqLogic->getCmd('info') as $cmd) {
				if ($eqLogic->getConfiguration('isNVR', false)) {
					if ($cmd->getLogicalId() == 'state') {
						$eqLogic->checkAndUpdateCmd($cmd, 1);
						continue;
					}
					if ($cmd->getLogicalId() == 'memory_used') {
						if (isset($datas['nvr']) && isset($datas['nvr']['systemInfo']) && isset($datas['nvr']['systemInfo']['memory'])) {
							$value = ($datas['nvr']['systemInfo']['memory']['total'] - $datas['nvr']['systemInfo']['memory']['available']) / $datas['nvr']['systemInfo']['memory']['total'];
							$eqLogic->checkAndUpdateCmd($cmd, round($value * 100, 2));
						}
						continue;
					}
					if ($cmd->getLogicalId() == 'tmpfs_used') {
						if (isset($datas['nvr']) && isset($datas['nvr']['systemInfo']) && isset($datas['nvr']['systemInfo']['tmpfs'])) {
							$value = ($datas['nvr']['systemInfo']['tmpfs']['total'] - $datas['nvr']['systemInfo']['tmpfs']['available']) / $datas['nvr']['systemInfo']['tmpfs']['total'];
							$eqLogic->checkAndUpdateCmd($cmd, round($value * 100, 2));
						}
						continue;
					}
				}

				$paths = explode('::', $cmd->getLogicalId());
				$value = $datas;
				foreach ($paths as $key) {
					if (!isset($value[$key])) {
						continue 2;
					}
					$value = $value[$key];
				}
				if (in_array($key, array('lastSeen', 'lastMotion', 'lastRing'))) {
					$value = date('Y-m-d H:i:s', $value / 1000);
				}
				if ($cmd->getLogicalId() == 'nvr::uptime') {
					$value = self::secondsToTime($value / 1000);
				}
				$eqLogic->checkAndUpdateCmd($cmd, $value);
			}
		}
		if (!config::byKey('dontGetEvent', 'unifiprotect', false)) {
			$raw_events = $controller->get_raw_events(strtotime('now -' . (10 * config::byKey('DeamonSleepTime', 'unifiprotect', 3, true)) . ' seconds') * 1000, strtotime('now +10min') * 1000);
			log::add('unifiprotect', 'debug', json_encode($raw_events));
			$events = array();
			foreach ($raw_events as $raw_event) {
				if (!isset($raw_event['camera']) || $raw_event['camera'] == '') {
					continue;
				}
				if (!isset($raw_event['start']) || $raw_event['start'] == '') {
					continue;
				}
				if (!isset($events[$raw_event['camera']])) {
					$events[$raw_event['camera']] = $raw_event;
				} elseif ($events[$raw_event['camera']]['start'] < $raw_event['start']) {
					$events[$raw_event['camera']] = $raw_event;
				}
			}
			foreach ($events as &$event) {
				if ($event['type'] == 'smartDetectZone' && isset($event['smartDetectTypes']) && isset($event['smartDetectTypes'][0])) {
					$event['type'] = $event['smartDetectTypes'][0];
				}
			}

			foreach ($eqLogics as $eqLogic) {
				if (!$eqLogic->getConfiguration('isCamera', false)) {
					continue;
				}
				if (!isset($events[$eqLogic->getConfiguration('device_id')])) {
					continue;
				}
				$found_event = $events[$eqLogic->getConfiguration('device_id')];
				if ($eqLogic->getCache('event::lastId') == $found_event['id']) {
					continue;
				}
				$eqLogic->checkAndUpdateCmd('event::lastDate', date('Y-m-d H:i:s', $found_event['start'] / 1000));
				$eqLogic->checkAndUpdateCmd('event::last', $found_event['type']);
				if (isset($found_event['score'])) {
					$eqLogic->checkAndUpdateCmd('event::lastScore', $found_event['score']);
				} else {
					$eqLogic->checkAndUpdateCmd('event::lastScore', '');
				}
				$eqLogic->setCache('event::lastId', $found_event['id']);
			}
		}
	}

	public function get_snapshot($_eqLogic) {
		$controller = self::getController();
		if (!is_object($controller)) {
			return null;
		}
		if ($_eqLogic->getEqType_name() == 'camera') {
			return $controller->get_snapshot($_eqLogic->getConfiguration('ip'));
		}
		return $controller->get_snapshot($_eqLogic->getConfiguration('device_id'));
	}


	/***********************Methode d'instance**************************/

	public function postSave() {
		if ($this->getConfiguration('applyType') != $this->getConfiguration('type')) {
			$this->applyModuleConfiguration();
		}
	}

	public function applyModuleConfiguration() {
		$this->setConfiguration('applyType', $this->getConfiguration('type'));
		if ($this->getConfiguration('type') == '') {
			$this->save();
			return true;
		}
		$device = self::devicesParameters($this->getConfiguration('type'));
		if (!is_array($device) || !isset($device['commands'])) {
			return true;
		}
		$this->import($device);
	}

	public function getImage() {
		if(method_exists($this,'getCustomImage')){
			$customImage = $this->getCustomImage();
			if($customImage !== null){
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
		if ($this->getLogicalId() == 'setNotification') {
			$controller = unifiprotect::getController();
			$controller->set_notification($_options['title'], $_options['message']);
			return;
		}
		unifiprotect::pull();
	}

	/************************Getteur Setteur****************************/
}
