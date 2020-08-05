<?php
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

namespace urbodus\wrestling;

use Exception;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use urbodus\wrestling\arena\Arena;
use urbodus\wrestling\arena\MapReset;
use urbodus\wrestling\command\CommandManager;
use urbodus\wrestling\entities\types\JoinEntity;
use urbodus\wrestling\entities\types\LeaderboardEntity;
use urbodus\wrestling\form\FormManager;
use urbodus\wrestling\provider\SQLite3Provider;
use urbodus\wrestling\provider\YamlProvider;
use urbodus\wrestling\scoreboard\ScoreboardStore;
use urbodus\wrestling\utils\Vector3;

class Wrestling extends PluginBase implements Listener
{
	/** @var Wrestling */
	private static $instance;

	/** @var Arena[] */
	public $arenas = [];

	/** @var ScoreboardStore */
	private $scoreboardStore;

	/** @var CommandManager */
	private $commandManager;

	/** @var Player[] */
	private $setters = [];

	/** @var array */
	private $setupData = [];

	/** @var FormManager */
	private $formManager;
	/**
	 * @var YamlProvider
	 */
	public $yamlProvider;
	/**
	 * @var SQLite3Provider
	 */
	private $sqliteProvider;

	/**
	 * @return Wrestling
	 */
	public static function getInstance(): Wrestling
	{
		return self::$instance;
	}

	public function onEnable()
	{
		$this->initVariables();
		$this->registerEntities();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info("§aPlugin initialized!");
		$this->getLogger()->info("§aAuthor: _LiTEK");
		$this->getLogger()->info("§aLICENSE: §2" . $this->getConfig()->get('license'));
		$this->getLogger()->info("§6Found §e(" . count($this->arenas) . ")§6 arenas");
	}

	public function onDisable()
	{
		$this->getSqliteProvider()->closeDatabase();
	}

	private function initVariables()
	{
		try {
			self::$instance = $this;
			$this->scoreboardStore = new ScoreboardStore($this);
			$this->commandManager = new CommandManager($this);
			$this->formManager = new FormManager($this);
			$this->yamlProvider = new YamlProvider($this);
			$this->sqliteProvider = new SQLite3Provider($this);
		} catch (Exception $exception) {
			$this->getLogger()->alert("Some variables could not be loaded: {$exception->getMessage()}");
		}
	}

	private function registerEntities()
	{
		$entities = [LeaderboardEntity::class, JoinEntity::class];
		foreach ($entities as $entity) {
			Entity::registerEntity($entity, true);
		}
	}

	/**
	 * @param string $arenaName
	 */
	public function addArena(string $arenaName)
	{
		$this->arenas[$arenaName] = new Arena($this, []);
	}

	/**
	 * @param Player $player
	 * @param string $arena
	 */
	public function setSetter(Player $player, string $arena)
	{
		$this->setters[$player->getName()] = $this->arenas[$arena];
		$player->teleport($this->getServer()->getLevelByName($arena)->getSafeSpawn());
	}

	/**
	 * @param string $gameType
	 * @return Arena|null
	 */
	public function getRandomArena(string $gameType = null): ?Arena
	{
		if ($gameType === null){
			foreach ($this->arenas as $name => $arena) {
				if (count($arena->players) > 0){
					return $arena;
				}
			}
			if (empty($this->arenas)){
				return null;
			}
			return $this->arenas[array_rand($this->arenas)];
		} else {
			$expectedArenas = [];
			foreach ($this->arenas as $name => $arena) {
				if ($arena->gameType === $gameType){
					$expectedArenas[] = $arena;
					if (count($arena->players) > 0){
						return $arena;
					}
				}
			}
			if (empty($expectedArenas)){
				return null;
			}
			return $expectedArenas[array_rand($expectedArenas)];
		}
	}

	/**
	 * @param PlayerChatEvent $event
	 */
	public function onChat(PlayerChatEvent $event)
	{
		$player = $event->getPlayer();

		if (!isset($this->setters[$player->getName()])) {
			return;
		}

		$event->setCancelled(true);
		$args = explode(" ", $event->getMessage());

		/** @var Arena $arena */
		$arena = $this->setters[$player->getName()];

		switch ($args[0]) {
			case "help":
				$player->sendMessage("§b§l» §r§7Wrestling Setup Help §8(§7§a1/1§8)\n" .
					"§3help : §7Displays list of available setup commands\n" .
					"§3slots : §7Updates arena slots\n" .
					"§3spawn : §7Sets slot number in arena\n" .
					"§3level : §7Sets arena level\n" .
					"§3gametype : §7Sets arena game type\n" .
					"§3savelevel : §7Saves the arena level\n" .
					"§3enable : §7Enables the arena");
				break;
			case "slots":
				if (!isset($args[1])) {
					$player->sendMessage("§c§l» §r§7Usage: slots <int: slots>");
					break;
				}
				$arena->data["slots"] = (int)$args[1];
				$player->sendMessage("§a§l» §r§7Slots updated to §a$args[1]!");
				break;
			case "level":
				if (!isset($args[1])) {
					$player->sendMessage("§c§l» §r§7Usage: §7level <levelName>");
					break;
				}
				if (!$this->getServer()->isLevelGenerated($args[1])) {
					$player->sendMessage("§c§l» §r§7Level $args[1] does not found!");
					break;
				}
				$player->sendMessage("§a§l» §r§7Arena level updated to $args[1]!");
				$arena->data["level"] = $args[1];
				break;
			case "spawn":
				if (!isset($args[1])) {
					$player->sendMessage("§c§l» §rUsage: §7setspawn <int: spawn>");
					break;
				}
				if (!is_numeric($args[1])) {
					$player->sendMessage("§c§l» §rType number!");
					break;
				}
				if ((int)$args[1] > $arena->data["slots"]) {
					$player->sendMessage("§c§l» §rThere are only {$arena->data["slots"]} slots!");
					break;
				}

				$arena->data["spawns"]["spawn-{$args[1]}"] = (new Vector3($player->getX(), $player->getY(), $player->getZ()))->__toString();
				$player->sendMessage("§a§l» §r§7Spawn §a$args[1]§7 set to §aX: §7" . (string)round($player->getX()) . " §aY: §7" . (string)round($player->getY()) . " §aZ: §7" . (string)round($player->getZ()));
				break;
			case "gametype":
				if (!isset($args[1])) {
					$player->sendMessage("§c§l» §r§7Usage: §7gametype <buhc|sumo|1vs1>");
					break;
				}
				if (!in_array($args[1], ['buhc', 'sumo', '1vs1'])) {
					$player->sendMessage("§c§l» §r§7Game type $args[1] is not available!");
					break;
				}
				$player->sendMessage("§a§l» §r§7Game type updated to $args[1]!");
				$arena->data["gametype"] = $args[1];
				break;
			case "savelevel":
				if (!$arena->level instanceof Level) {
					$levelName = $arena->data["level"];
					if (!is_string($levelName) || !$this->getServer()->isLevelGenerated($levelName)) {
						errorMessage:
						$player->sendMessage("§c§l» §r§7Error while saving the level: world not found.");
						if ($arena->setup) {
							$player->sendMessage("§6§l» §r§7Try save level after enabling the arena.");
						}
						return;
					}
					if (!$this->getServer()->isLevelLoaded($levelName)) {
						$this->getServer()->loadLevel($levelName);
					}

					try {
						if (!$arena->mapReset instanceof MapReset) {
							goto errorMessage;
						}
						$arena->mapReset->saveMap($this->getServer()->getLevelByName($levelName));
						$player->sendMessage("§a§l» §r§7Level saved!");
					} catch (Exception $exception) {
						goto errorMessage;
					}
					break;
				}
				break;
			case "enable":
				if (!$arena->setup) {
					$player->sendMessage("§6§l» §r§7Arena is already enabled!");
					break;
				}

				if (!$arena->enable(false)) {
					$player->sendMessage("§c§l» §r§7Could not load arena, there are missing information!");
					break;
				}

				if ($this->getServer()->isLevelGenerated($arena->data["level"])) {
					if (!$this->getServer()->isLevelLoaded($arena->data["level"]))
						$this->getServer()->loadLevel($arena->data["level"]);
					if (!$arena->mapReset instanceof MapReset)
						$arena->mapReset = new MapReset($arena);
					$arena->mapReset->saveMap($this->getServer()->getLevelByName($arena->data["level"]));
				}
				if(is_file($file = $this->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $arena->data["level"] . ".yml")) {
					$config = new Config($file, Config::YAML);
					$config->setAll($arena->data);
					$config->save();
				}
				$arena->loadArena(false);
				$player->sendMessage("§a§l» §r§7Arena enabled!");
				break;
			case "done":
				$player->sendMessage("§a§l» §r§7You have successfully left setup mode!");
				$this->removeSetter($player);
				if (isset($this->setupData[$player->getName()])) {
					unset($this->setupData[$player->getName()]);
				}
				break;
			default:
				$player->sendMessage("§3You are now in setup mode.\n" .
					"§b§l» §7use §3help §7to display available commands\n" .
					"§b§l» §7or §3done §7to leave setup mode");
				break;
		}
	}

	/**
	 * @param Player $player
	 */
	public function removeSetter(Player $player)
	{
		unset($this->setters[$player->getName()]);
	}

	/**
	 * @param Player $player
	 * @return bool
	 */
	public function isSetter(Player $player)
	{
		return in_array($player->getName(), $this->setters);
	}

	/**
	 * @param EntityDamageByEntityEvent $event
	 */
	public function onDamage(EntityDamageByEntityEvent $event)
	{
		$player = $event->getDamager();
		$entity = $event->getEntity();
		if ($player instanceof Player && $entity instanceof LeaderboardEntity) {
			$event->setCancelled();
		}
		if ($player instanceof Player && $entity instanceof JoinEntity) {
			$event->setCancelled();
			if (!$this->getSqliteProvider()->verifyPlayerInDB($player)){
				$this->getSqliteProvider()->addNewPlayer($player);
			}
			$this->getFormManager()->sendGamePanel($player);
		}
	}

	/**
	 * @return ScoreboardStore
	 */
	public function getStore(): ScoreboardStore
	{
		return $this->scoreboardStore;
	}

	/**
	 * @return CommandManager
	 */
	public function getCommandManager(): CommandManager
	{
		return $this->commandManager;
	}

	/**
	 * @return FormManager
	 */
	public function getFormManager(): FormManager
	{
		return $this->formManager;
	}

	/**
	 * @return YamlProvider
	 */
	public function getYamlProvider(): YamlProvider
	{
		return $this->yamlProvider;
	}

	/**
	 * @return SQLite3Provider
	 */
	public function getSqliteProvider(): SQLite3Provider
	{
		return $this->sqliteProvider;
	}

}