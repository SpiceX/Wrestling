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

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
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

	const MINIMUM_VOID = 15;

	/** @var Wrestling $plugin */
	public $plugin;

	/** @var string */
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
	public $bossbar;

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

		$this->gameType = $this->data["gametype"];
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
			$player->setImmobile(false);
			$this->scoreboard->showTo($player);
		}

		$this->players = $players;
		$this->phase = 1;
		$this->setArmor($this->gameType);
		$this->broadcastMessage("§9FIGHT!", self::MSG_TITLE);
	}

	private function setArmor(string $gameType)
	{
		switch ($gameType) {
			case Game::SUMO:
				foreach ($this->players as $player) {
					$player->getArmorInventory()->clearAll();
					$player->getInventory()->clearAll();
					$player->removeAllEffects();
					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION),20*90,3,false));
					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::DAMAGE_RESISTANCE),20*90,3,false));
				}
				break;
			case Game::BUHC:
				$helmet = Item::get(Item::DIAMOND_HELMET);
				$chestplate = Item::get(Item::DIAMOND_CHESTPLATE);
				$leggings = Item::get(Item::DIAMOND_LEGGINGS);
				$boots = Item::get(Item::DIAMOND_BOOTS);
				$sword = Item::get(Item::DIAMOND_SWORD);
				$bow = Item::get(Item::BOW);
				$helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
				$chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION), 2));
				$leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
				$boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
				$sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::SHARPNESS)));
				$bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::POWER)));
				foreach ($this->players as $player) {
					$player->getArmorInventory()->setHelmet($helmet);
					$player->getArmorInventory()->setChestplate($chestplate);
					$player->getArmorInventory()->setLeggings($leggings);
					$player->getArmorInventory()->setBoots($boots);
					$player->getInventory()->setItem(0, $sword);
					$player->getInventory()->setItem(1, $bow);
					$player->getInventory()->setItem(2, Item::get(Item::BUCKET, 10));
					$player->getInventory()->setItem(3, Item::get(Item::WOODEN_PLANKS, 0, 64));
					$player->getInventory()->setItem(4, Item::get(Item::WOODEN_PLANKS, 0, 64));
					$player->getInventory()->setItem(5, Item::get(Item::GOLDEN_APPLE, 0, 5));
					$player->getInventory()->setItem(6, Item::get(Item::BUCKET, 8));
					$player->getInventory()->setItem(7, Item::get(Item::FISHING_ROD));
					$player->getInventory()->addItem(Item::get(Item::ARROW, 0, 64));
				}
				break;
			case Game::ONEVSONE:
				$helmet = Item::get(Item::DIAMOND_HELMET);
				$chestplate = Item::get(Item::DIAMOND_CHESTPLATE);
				$leggings = Item::get(Item::DIAMOND_LEGGINGS);
				$boots = Item::get(Item::DIAMOND_BOOTS);
				$sword = Item::get(Item::DIAMOND_SWORD);
				$helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
				$chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION), 2));
				$leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
				$boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
				$sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::SHARPNESS)));
				foreach ($this->players as $player) {
					$player->getArmorInventory()->setHelmet($helmet);
					$player->getArmorInventory()->setChestplate($chestplate);
					$player->getArmorInventory()->setLeggings($leggings);
					$player->getArmorInventory()->setBoots($boots);
					$player->getInventory()->setItem(0, $sword);
					$player->getInventory()->setItem(1, Item::get(Item::ENDER_PEARL, 0, 16));
					$player->getInventory()->setItem(2, Item::get(Item::STEAK, 0, 32));
					for ($i = 3; $i < 8; $i++) {
						$player->getInventory()->addItem(Item::get(Item::SPLASH_POTION, 22));
					}

				}
				break;
		}
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

	/**
	 * @param string $sound
	 */
	public function broadcastSound(string $sound): void
	{
		foreach ($this->players as $player) {
			Utils::playSound($player, $sound);
		}
	}

	/**
	 * @param int $id
	 * @param array $targetData
	 * @param int $padding
	 */
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
				foreach ($this->players as $player) {
					$this->scoreboard->setLine(2, "§7-------------");
					$this->scoreboard->setLine(3, "§bUser: §7" . $player->getName());
					$this->scoreboard->setLine(4, "§bGame: §7" . $this->gameType);
					$this->scoreboard->setLine(5, "§bStatus: §3In Game");
					$this->scoreboard->setLine(6, "§7-------------");
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

		$player->sendTitle(Utils::addGuillemets("§aVictory!"));
		Utils::playSound($player, "random.levelup");
		$player->teleport($this->level->getSafeSpawn());
		$player->setImmobile(true);
		$this->plugin->getSqliteProvider()->addWin($player, $this->gameType);
		$this->plugin->getServer()->broadcastMessage("§l§a» §r§7[Wrestling] Player §b{$player->getName()} §7won the game at §b{$this->level->getFolderName()}!");
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
			switch ($this->phase){
				case self::PHASE_GAME:
					if ($player->getY() <= self::MINIMUM_VOID) {
						$this->toRespawn[$player->getName()] = $player;
						$this->disconnectPlayer($player, "", true);
						$this->broadcastMessage("§a§l» §r§7{$player->getName()} has fallen into void §7[" . count($this->players) . "/{$this->data["slots"]}]");
					}
					break;
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
		$player->setImmobile(false);
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$this->scoreboard->hideFrom($player);
		$this->bossbar->hideFrom($player);
		if ($player->isOnFire()){
			$player->extinguish();
		}
		$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());

		if (!$death) {
			$this->broadcastMessage("§a§l» §r§7Player {$player->getName()} left the game. §7[" . count($this->players) . "/{$this->data["slots"]}]");
		}

		if ($death) {
			$player->addEffect(new EffectInstance(Effect::getEffect(Effect::BLINDNESS), 2));
			$player->sendTitle(Utils::addGuillemets("§r§cDEAD"));
			$this->plugin->getSqliteProvider()->addLose($player, $this->gameType);
		}

		if ($quitMsg != "") {
			$player->sendMessage("§a§l» §r§7 $quitMsg");
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 */
	public function onBreak(BlockBreakEvent $event)
	{
		$player = $event->getPlayer();
		if ($this->inGame($player)) {
			switch ($this->gameType) {
				case self::SUMO:
				case self::ONEVSONE:
					$event->setCancelled(true);
					break;
			}
		}
	}

	/**
	 * @param EntityDamageEvent $event
	 */
	public function onEntityDamage(EntityDamageEvent $event)
	{
		$player = $event->getEntity();
		if ($player instanceof Player && $this->inGame($player)) {
			switch ($this->gameType) {
				case Game::SUMO:
					if ($this->phase !== self::PHASE_GAME){
						$event->setCancelled(true);
						return;
					}
					if ($event->getCause() === EntityDamageEvent::CAUSE_VOID) {
						$this->toRespawn[$player->getName()] = $player;
						$this->disconnectPlayer($player, "", true);
						$this->broadcastMessage("§a§l» §r§7{$player->getName()} has fallen into void §7[" . count($this->players) . "/{$this->data["slots"]}]");
					}
					break;
				case Game::ONEVSONE:
				case Game::BUHC:
					if ($this->phase !== self::PHASE_GAME){
						$event->setCancelled();
						return;
					}
					if ($event->getFinalDamage() > $player->getHealth()) {
						$this->toRespawn[$player->getName()] = $player;
						$this->disconnectPlayer($player, "", true);
						$this->broadcastMessage("§a§l» §r§7{$player->getName()} has dead §7[" . count($this->players) . "/{$this->data["slots"]}]");
					}
					break;
			}
		}
	}

	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event)
	{
		$player = $event->getEntity();
		$damager = $event->getDamager();
		if ($player instanceof Player && $this->inGame($player) && $damager instanceof Player) {
			switch ($this->gameType) {
				case Game::SUMO:
					$player->setHealth(21.0);
					$damager->setHealth(21.0);
					break;
				case Game::ONEVSONE:
				case Game::BUHC:
					if ($event->getFinalDamage() > $player->getHealth()) {
						$this->toRespawn[$player->getName()] = $player;
						$this->disconnectPlayer($player, "", true);
						$this->broadcastMessage("§a§l» §r§7{$player->getName()} has dead §7[" . count($this->players) . "/{$this->data["slots"]}]");
					}
					break;
			}
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
	 * @param Player $player
	 */
	public function joinToArena(Player $player)
	{
		if (!$this->data["enabled"]) {
			$player->sendMessage("§c§l» §r§7Arena is under setup!");
			return;
		}

		if ($this->phase !== self::PHASE_LOBBY) {
			$player->sendMessage("§c§l» §r§7Arena is full!");
			return;
		}

		if (count($this->players) >= $this->data["slots"]) {
			$player->sendMessage("§c§l» §r§7Arena is full!");
			return;
		}

		if ($this->inGame($player)) {
			$player->sendMessage("§c§l» §r§7You are already in game!");
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

		switch ($this->gameType) {
			case self::ONEVSONE:
				$player->sendMessage("§b1vs1 §7is a competitive minigame played within Minecraft.\nWhen playing §b1vs1§7, players need to beat their oponent, wining the duel.\nWin all matches and see your §6stats.");
				break;
			case self::SUMO:
				$player->sendMessage("§bSumo §7is a competitive minigame played within Minecraft.\nWhen playing §bSumo§7, players need to hitting their oponent off the platform, wining the duel.\nWin all matches and see your §6stats.");
				break;
			case self::BUHC:
				$player->sendMessage("§bBUHC §7is a competitive minigame played within Minecraft.\nWhen playing §bBUHC§7, players need to beat their opponent in the arena, wining the duel.\nWin all matches and see your §6stats.");
				break;
		}

		Utils::playSound($player, "random.orb");
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->setXpLevel(0);
		$player->setXpProgress(0.0);
		$player->setGamemode($player::ADVENTURE);
		$player->setImmobile(true);
		$player->setHealth(20);
		$player->setFood(20);
		$this->bossbar->showTo($player);
		$this->broadcastMessage("§a§l» §r§7Player {$player->getName()} joined! §7[" . count($this->players) . "/{$this->data["slots"]}]");
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
		$this->broadcastMessage("§a§l» §r§7{$this->plugin->getServer()->getLanguage()->translate($event->getDeathMessage())} §7[" . count($this->players) . "/{$this->data["slots"]}]");
		$event->setDeathMessage("");
		$event->setDrops([]);
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