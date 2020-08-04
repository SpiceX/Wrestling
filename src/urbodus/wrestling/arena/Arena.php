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

namespace urbodus\wrestling\arena;

use pocketmine\block\Block;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\tile\Tile;
use urbodus\wrestling\bossbar\BossBar;
use urbodus\wrestling\scoreboard\Scoreboard;
use urbodus\wrestling\task\GameTask;
use urbodus\wrestling\utils\Padding;
use urbodus\wrestling\utils\Utils;
use urbodus\wrestling\utils\Vector3;
use urbodus\wrestling\Wrestling;

/**
 * Class Arena
 * @package wrestling\arena
 */
class Arena implements Listener, Game
{

	const MSG_MESSAGE = 0;
	const MSG_TIP = 1;
	const MSG_POPUP = 2;
	const MSG_TITLE = 3;

	const BOSSBAR_UPDATE = 4;
	const SCOREBOARD_UPDATE = 5;

	const PHASE_LOBBY = 0;
	const PHASE_GAME = 1;
	const PHASE_RESTART = 2;

	/** @var Wrestling $plugin */
	public $plugin;

	/** @var int */
	public $gameType;

	/** @var GameTask $scheduler */
	public $scheduler;

	/** @var MapReset $mapReset */
	public $mapReset;

	/** @var int $phase */
	public $phase = 0;

	/** @var array $data */
	public $data = [];

	/** @var bool $setting */
	public $setup = false;

	/** @var Player[] $players */
	public $players = [];

	/** @var Player[] $toRespawn */
	public $toRespawn = [];

	/** @var Level $level */
	public $level = null;

	/** @var BossBar */
	private $bossbar;

	/** @var Scoreboard */
	private $scoreboard;

	/**
	 * Arena constructor.
	 * @param Wrestling $plugin
	 * @param array $arenaFileData
	 */
	public function __construct(Wrestling $plugin, array $arenaFileData)
	{
		$this->plugin = $plugin;
		$this->data = $arenaFileData;
		$this->setup = !$this->enable(false);

		$this->plugin->getScheduler()->scheduleRepeatingTask($this->scheduler = new GameTask($this), 20);
		$this->bossbar = new BossBar(Utils::addGuillemets("§r§7..."), 1, 1);
		$this->scoreboard = new Scoreboard($plugin, Utils::addGuillemets("§a§lWRESTLING"), Scoreboard::ACTION_CREATE);
		$this->scoreboard->create(Scoreboard::DISPLAY_MODE_SIDEBAR, Scoreboard::SORT_DESCENDING, true);
		$this->scoreboard = new Scoreboard($plugin, Utils::addGuillemets("§a§lWRESTLING"), Scoreboard::ACTION_MODIFY);

		if ($this->setup) {
			if (empty($this->data)) {
				$this->createBasicData();
			}
		} else {
			$this->loadArena();
		}
	}

	/**
	 * @param bool $loadArena
	 * @return bool $isEnabled
	 */
	public function enable(bool $loadArena = true): bool
	{
		if (empty($this->data)) {
			return false;
		}
		if ($this->data["level"] == null) {
			return false;
		}
		if ($this->data["gametype"] == null) {
			return false;
		}
		if (!$this->plugin->getServer()->isLevelGenerated($this->data["level"])) {
			return false;
		}
		if (!is_int($this->data["slots"])) {
			return false;
		}
		if (!is_array($this->data["spawns"])) {
			return false;
		}
		if (count($this->data["spawns"]) != $this->data["slots"]) {
			return false;
		}
		$this->data["enabled"] = true;
		$this->setup = false;
		if ($loadArena) {
			$this->loadArena();
		}
		return true;
	}

	/**
	 * @param bool $restart
	 */
	public function loadArena(bool $restart = false)
	{
		if (!$this->data["enabled"]) {
			$this->plugin->getLogger()->error("Can not load arena: Arena is not enabled!");
			return;
		}

		if (!$this->mapReset instanceof MapReset) {
			$this->mapReset = new MapReset($this);
		}

		if (!$restart) {
			$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		} else {
			$this->scheduler->reloadTimer();
			$this->level = $this->mapReset->loadMap($this->data["level"]);
		}

		if (!$this->level instanceof Level) {
			$level = $this->mapReset->loadMap($this->data["level"]);
			if (!$level instanceof Level) {
				$this->plugin->getLogger()->error("Arena level wasn't found. Try save level in setup mode.");
				$this->setup = true;
				return;
			}
			$this->level = $level;
		}


		$this->phase = static::PHASE_LOBBY;
		$this->players = [];
	}

	private function createBasicData()
	{
		$this->data = [
			"level" => null,
			"slots" => 4,
			"spawns" => [],
			"enabled" => false,
		];
	}

	public function startGame()
	{
		$players = [];
		foreach ($this->players as $player) {
			$players[$player->getName()] = $player;
			$player->setGamemode($player::SURVIVAL);
		}


		$this->players = $players;
		$this->phase = 1;

		$this->broadcastMessage("Game Started!", self::MSG_TITLE);
	}

	/**
	 * @param string $message
	 * @param int $id
	 * @param string $subMessage
	 */
	public function broadcastMessage(string $message, int $id = 0, string $subMessage = "")
	{
		foreach ($this->players as $player) {
			switch ($id) {
				case self::MSG_MESSAGE:
					$player->sendMessage($message);
					break;
				case self::MSG_TIP:
					$player->sendTip($message);
					break;
				case self::MSG_POPUP:
					$player->sendPopup($message);
					break;
				case self::MSG_TITLE:
					$player->sendTitle($message, $subMessage);
					break;
			}
		}
	}

	public function updateTargets(int $id, array $targetData = [], int $padding = 0): void
	{
		switch ($id) {
			case self::BOSSBAR_UPDATE:
				foreach ($this->players as $player) {
					if ($padding === Padding::PADDING_CENTER) {
						$this->bossbar->updateFor($player, Padding::centerText($targetData[0]));
					}
					if ($padding === Padding::PADDING_LINE) {
						$this->bossbar->updateFor($player, Padding::centerLine($targetData[0]));
					}
				}
				break;
			case self::SCOREBOARD_UPDATE:
				$this->scoreboard->removeLines();
				foreach ($targetData as $lineIndex => $text) {
					$this->scoreboard->setLine($lineIndex, $text);
				}
				foreach ($this->players as $player) {
					$this->scoreboard->showTo($player);
				}
				break;
		}
	}

	public function startRestart()
	{
		$player = null;
		foreach ($this->players as $p) {
			$player = $p;
		}

		if ($player === null || (!$player instanceof Player) || (!$player->isOnline())) {
			$this->phase = self::PHASE_RESTART;
			return;
		}

		$player->sendTitle("§aYOU WON!");
		$this->plugin->getServer()->broadcastMessage("§a[Wrestling] Player {$player->getName()} won the game at {$this->level->getFolderName()}!");
		$this->phase = self::PHASE_RESTART;
	}

	/**
	 * @return bool $end
	 */
	public function checkEnd(): bool
	{
		return count($this->players) <= 1;
	}

	/**
	 * @param PlayerMoveEvent $event
	 */
	public function onMove(PlayerMoveEvent $event)
	{
		if ($this->phase != self::PHASE_LOBBY) return;
		$player = $event->getPlayer();
		if ($this->inGame($player)) {
			$index = null;
			foreach ($this->players as $i => $p) {
				if ($p->getId() == $player->getId()) {
					$index = $i;
				}
			}
			if ($event->getPlayer()->asVector3()->distance(Vector3::fromString($this->data["spawns"][$index])) > 1) {
				$player->teleport(Vector3::fromString($this->data["spawns"][$index]));
			}
		}
	}

	/**
	 * @param Player $player
	 * @return bool $isInGame
	 */
	public function inGame(Player $player): bool
	{
		switch ($this->phase) {
			case self::PHASE_LOBBY:
				$inGame = false;
				foreach ($this->players as $players) {
					if ($players->getId() == $player->getId()) {
						$inGame = true;
					}
				}
				return $inGame;
			default:
				return isset($this->players[$player->getName()]);
		}
	}

	/**
	 * @param PlayerExhaustEvent $event
	 */
	public function onExhaust(PlayerExhaustEvent $event)
	{
		$player = $event->getPlayer();

		if (!$player instanceof Player) return;

		if ($this->inGame($player) && $this->phase == self::PHASE_LOBBY) {
			$event->setCancelled(true);
		}
	}

	/**
	 * @param PlayerInteractEvent $event
	 */
	public function onInteract(PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$block = $event->getBlock();

		if ($this->inGame($player) && $event->getBlock()->getId() == Block::CHEST && $this->phase == self::PHASE_LOBBY) {
			$event->setCancelled(true);
			return;
		}

		if (!$block->getLevel()->getTile($block) instanceof Tile) {
			return;
		}

		if ($this->phase == self::PHASE_GAME) {
			$player->sendMessage("§c> Arena is in-game");
			return;
		}
		if ($this->phase == self::PHASE_RESTART) {
			$player->sendMessage("§c> Arena is restarting!");
			return;
		}

		if ($this->setup) {
			return;
		}

		$this->joinToArena($player);
	}

	/**
	 * @param Player $player
	 */
	public function joinToArena(Player $player)
	{
		if (!$this->data["enabled"]) {
			$player->sendMessage("§c> Arena is under setup!");
			return;
		}

		if (count($this->players) >= $this->data["slots"]) {
			$player->sendMessage("§c> Arena is full!");
			return;
		}

		if ($this->inGame($player)) {
			$player->sendMessage("§c> You are already in game!");
			return;
		}

		$selected = false;
		for ($lS = 1; $lS <= $this->data["slots"]; $lS++) {
			if (!$selected) {
				if (!isset($this->players[$index = "spawn-{$lS}"])) {
					$player->teleport(Position::fromObject(Vector3::fromString($this->data["spawns"][$index]), $this->level));
					$this->players[$index] = $player;
					$selected = true;
				}
			}
		}

		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();

		$player->setGamemode($player::ADVENTURE);
		$player->setHealth(20);
		$player->setFood(20);

		$this->broadcastMessage("§a> Player {$player->getName()} joined! §7[" . count($this->players) . "/{$this->data["slots"]}]");
	}

	/**
	 * @param PlayerDeathEvent $event
	 */
	public function onDeath(PlayerDeathEvent $event)
	{
		$player = $event->getPlayer();

		if (!$this->inGame($player)) return;

		foreach ($event->getDrops() as $item) {
			$player->getLevel()->dropItem($player, $item);
		}
		$this->toRespawn[$player->getName()] = $player;
		$this->disconnectPlayer($player, "", true);
		$this->broadcastMessage("§a> {$this->plugin->getServer()->getLanguage()->translate($event->getDeathMessage())} §7[" . count($this->players) . "/{$this->data["slots"]}]");
		$event->setDeathMessage("");
		$event->setDrops([]);
	}

	/**
	 * @param Player $player
	 * @param string $quitMsg
	 * @param bool $death
	 */
	public function disconnectPlayer(Player $player, string $quitMsg = "", bool $death = false)
	{
		switch ($this->phase) {
			case Arena::PHASE_LOBBY:
				$index = "";
				foreach ($this->players as $i => $p) {
					if ($p->getId() == $player->getId()) {
						$index = $i;
					}
				}
				if ($index != "") {
					unset($this->players[$index]);
				}
				break;
			default:
				unset($this->players[$player->getName()]);
				break;
		}

		$player->removeAllEffects();

		$player->setGamemode($this->plugin->getServer()->getDefaultGamemode());

		$player->setHealth(20);
		$player->setFood(20);

		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();

		$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());

		if (!$death) {
			$this->broadcastMessage("§a> Player {$player->getName()} left the game. §7[" . count($this->players) . "/{$this->data["slots"]}]");
		}

		if ($quitMsg != "") {
			$player->sendMessage("§a> $quitMsg");
		}
	}

	/**
	 * @param PlayerRespawnEvent $event
	 */
	public function onRespawn(PlayerRespawnEvent $event)
	{
		$player = $event->getPlayer();
		if (isset($this->toRespawn[$player->getName()])) {
			$event->setRespawnPosition($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
			unset($this->toRespawn[$player->getName()]);
		}
	}

	/**
	 * @param PlayerQuitEvent $event
	 */
	public function onQuit(PlayerQuitEvent $event)
	{
		if ($this->inGame($event->getPlayer())) {
			$this->disconnectPlayer($event->getPlayer());
		}
	}

	/**
	 * @param EntityLevelChangeEvent $event
	 */
	public function onLevelChange(EntityLevelChangeEvent $event)
	{
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if ($this->inGame($player)) {
			$this->disconnectPlayer($player, "You have successfully left the game!");
		}
	}

	public function __destruct()
	{
		unset($this->scheduler);
	}
}