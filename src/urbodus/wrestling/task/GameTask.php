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
namespace urbodus\wrestling\task;

use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\scheduler\Task;
use urbodus\wrestling\arena\Arena;
use urbodus\wrestling\utils\Time;

/**
 * Class ArenaScheduler
 * @package urbodus\wrestling\task
 */
class GameTask extends Task
{

	/** @var Arena $plugin */
	protected $plugin;

	/** @var int $startTime */
	public $startTime = 40;

	/** @var float|int $gameTime */
	public $gameTime = 20 * 60;

	/** @var int $restartTime */
	public $restartTime = 10;


	/**
	 * ArenaScheduler constructor.
	 * @param Arena $plugin
	 */
	public function __construct(Arena $plugin)
	{
		$this->plugin = $plugin;
	}

	/**
	 * @param int $currentTick
	 */
	public function onRun(int $currentTick)
	{

		if ($this->plugin->setup) return;

		switch ($this->plugin->phase) {
			case Arena::PHASE_LOBBY:
				if (count($this->plugin->players) >= 2) {
					$this->plugin->broadcastMessage("§a> Starting in " . Time::calculateTime($this->startTime) . " sec.", Arena::MSG_TIP);
					$this->startTime--;
					if ($this->startTime == 0) {
						$this->plugin->startGame();
						foreach ($this->plugin->players as $player) {
							$this->plugin->level->addSound(new AnvilUseSound($player->asVector3()));
						}
					} else {
						foreach ($this->plugin->players as $player) {
							$this->plugin->level->addSound(new ClickSound($player->asVector3()));
						}
					}
				} else {
					$this->plugin->broadcastMessage("§c> You need more players to start a game!", Arena::MSG_TIP);
					$this->startTime = 40;
				}
				break;
			case Arena::PHASE_GAME:
				$this->plugin->broadcastMessage("§a> There are " . count($this->plugin->players) . " players, time to end: " . Time::calculateTime($this->gameTime) . "", Arena::MSG_TIP);
				switch ($this->gameTime) {
					case 15 * 60:
						$this->plugin->broadcastMessage("§a> All chests will be refilled in 5 min.");
						break;
					case 11 * 60:
						$this->plugin->broadcastMessage("§a> All chest will be refilled in 1 min.");
						break;
					case 10 * 60:
						$this->plugin->broadcastMessage("§a> All chests are refilled.");
						break;
				}
				if ($this->plugin->checkEnd()) $this->plugin->startRestart();
				$this->gameTime--;
				break;
			case Arena::PHASE_RESTART:
				$this->plugin->broadcastMessage("§a> Restarting in {$this->restartTime} sec.", Arena::MSG_TIP);
				$this->restartTime--;

				switch ($this->restartTime) {
					case 0:

						foreach ($this->plugin->players as $player) {
							$player->teleport($this->plugin->plugin->getServer()->getDefaultLevel()->getSpawnLocation());

							$player->getInventory()->clearAll();
							$player->getArmorInventory()->clearAll();
							$player->getCursorInventory()->clearAll();

							$player->setFood(20);
							$player->setHealth(20);

							$player->setGamemode($this->plugin->plugin->getServer()->getDefaultGamemode());
						}
						$this->plugin->loadArena(true);
						$this->reloadTimer();
						break;
				}
				break;
		}
	}


	public function reloadTimer()
	{
		$this->startTime = 30;
		$this->gameTime = 20 * 60;
		$this->restartTime = 10;
	}
}