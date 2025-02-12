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

namespace urbodus\wrestling\command\types;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use urbodus\wrestling\arena\Game;
use urbodus\wrestling\command\utils\Command;
use urbodus\wrestling\utils\Utils;

class UserCommand extends Command
{

	/**
	 * @param CommandSender $sender
	 * @param string $commandLabel
	 * @param array $args
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		if (isset($args[0]) && $sender instanceof Player) {
			switch ($args[0]) {
				case 'help':
					Utils::playSound($sender, "note.xylophone");
					$sender->sendMessage("§b§l» §r§7Wrestling User Command §8(§7a1/1§8)\n" .
						"§3/wrestling join §7<optional: Game> - Join a random or specific game\n" .
						"§3/wrestling info §7 - Show information about the plugin\n" .
						"§3/wrestling stats §7 - See your pvp stats\n");
					break;
				case 'join':
					if (isset($args[1])) {
						if (!$this->getPlugin()->getSqliteProvider()->verifyPlayerInDB($sender)) {
							$this->getPlugin()->getSqliteProvider()->addNewPlayer($sender);
						}
						switch ($args[1]) {
							case 'buhc':
								$arena = $this->getPlugin()->getRandomArena(Game::BUHC);
								if ($arena != null) {
									$arena->joinToArena($sender);
								} else {
									Utils::playSound($sender, "note.bassattack");
									$sender->sendMessage("§c§l» §r§7There is not available arenas!");
								}
								break;
							case 'sumo':
								$arena = $this->getPlugin()->getRandomArena(Game::SUMO);
								if ($arena != null) {
									$arena->joinToArena($sender);
								} else {
									Utils::playSound($sender, "note.bassattack");
									$sender->sendMessage("§c§l» §r§7There is not available arenas!");
								}
								break;
							case '1vs1':
								$arena = $this->getPlugin()->getRandomArena(Game::ONEVSONE);
								if ($arena != null) {
									$arena->joinToArena($sender);
								} else {
									Utils::playSound($sender, "note.bassattack");
									$sender->sendMessage("§c§l» §r§7There is not available arenas!");
								}
								break;
							default:
								Utils::playSound($sender, "note.bassattack");
								$sender->sendMessage("§c§l» §r§7Usage: /wrestling join <buhc|sumo|1vs1>");
						}
					} else {
						if (!$this->getPlugin()->getSqliteProvider()->verifyPlayerInDB($sender)) {
							$this->getPlugin()->getSqliteProvider()->addNewPlayer($sender);
						}
						$arena = $this->getPlugin()->getRandomArena(null);
						if ($arena != null) {
							$arena->joinToArena($sender);
						} else {
							Utils::playSound($sender, "note.bassattack");
							$sender->sendMessage("§c§l» §r§7There is not available arenas!");
						}
					}
					break;
				case 'stats':
					if ($this->getPlugin()->getSqliteProvider()->verifyPlayerInDB($sender)) {
						$sender->sendMessage($this->getPlugin()->getSqliteProvider()->getScore($sender));
					} else {
						Utils::playSound($sender, "note.didgeridoo");
						$sender->sendMessage("§c§l» §r§7 You never played wrestling on this server!");
					}
					break;
				case 'info':
					$sender->sendMessage("§bPlugin made by LiTEK_");
					$sender->sendMessage("§3Twitter: §b@LiTEK_");
					$sender->sendMessage("§6Comissions always open");
					break;
				default:
					$sender->sendMessage("§c§l» §r§7Usage: /wrestling help");
			}
		}
	}
}