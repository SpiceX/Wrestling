<?php /** @noinspection PhpUnused */
/**
 * Copyright 2018-2020 LiTEK - Josewowgame2888
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
declare(strict_types=1);
namespace urbodus\wrestling\provider;

use pocketmine\level\Level;
use pocketmine\utils\Config;
use urbodus\wrestling\arena\Arena;
use urbodus\wrestling\Wrestling;

/**
 * Class YamlDataProvider
 * @package wrestling\provider
 */
class YamlProvider {

	/** @var Wrestling $plugin */
	private $plugin;

	/**
	 * YamlDataProvider constructor.
	 * @param Wrestling $plugin
	 */
	public function __construct(Wrestling $plugin) {
		$this->plugin = $plugin;
		$this->init();
		$this->loadArenas();
	}

	public function init() {
		if(!is_dir($this->getDataFolder())) {
			@mkdir($this->getDataFolder());
		}
		if(!is_dir($this->getDataFolder() . "arenas")) {
			@mkdir($this->getDataFolder() . "arenas");
		}
		if(!is_dir($this->getDataFolder() . "saves")) {
			@mkdir($this->getDataFolder() . "saves");
		}
		$this->plugin->saveDefaultConfig();
		$this->plugin->saveResource("database.sq3");
	}

	public function loadArenas() {
		foreach (glob($this->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . "*.yml") as $arenaFile) {
			$config = new Config($arenaFile, Config::YAML);
			$this->plugin->arenas[basename($arenaFile, ".yml")] = new Arena($this->plugin, $config->getAll(false));
		}
	}

	public function saveArenas() {
		foreach ($this->plugin->arenas as $fileName => $arena) {
			if($arena->level instanceof Level) {
				foreach ($arena->players as $player) {
					$player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
				}
				$arena->mapReset->loadMap($arena->level->getFolderName(), true);
			}
			$config = new Config($this->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $fileName . ".yml", Config::YAML);
			$config->setAll($arena->data);
			$config->save();
		}
	}

	/**
	 * @return string $dataFolder
	 */
	private function getDataFolder(): string {
		return $this->plugin->getDataFolder();
	}
}