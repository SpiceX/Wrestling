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
use pocketmine\command\ConsoleCommandSender;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use urbodus\wrestling\command\utils\Command;
use urbodus\wrestling\entities\types\JoinEntity;

class ArenaSetupCommand extends Command
{

	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		if ($sender instanceof ConsoleCommandSender or !$sender->isOp()) {
			$sender->sendMessage("§l§c» §r§7You cannot use this command!");
			return;
		}
		if (isset($args[0]) && $sender instanceof Player) {
			switch ($args[0]) {
				case 'help':
					$sender->sendMessage("§b§l» §r§7Wrestling Setup Command §8(§7a1/1§8)\n" .
						"§3/wg create §7<arenaName> - Creates a new arena\n".
					"§3/wg arenas §7 - See arena list\n".
					"§3/wg remove §7<arenaName> - Remove an arena\n".
					"§3/wg tops §7 - Place leaderboard\n");
					break;
				case 'create':
					if (!isset($args[1])) {
						$sender->sendMessage("§c§l» §r§7/wg create <arenaName>");
						break;
					}
					if ($this->getPlugin()->isSetter($sender)) {
						$sender->sendMessage("§c§l» §r§7You are already in setup mode!");
						break;
					}
					if (!Server::getInstance()->isLevelGenerated($args[1])) {
						$sender->sendMessage("§c§l» §r§7There is no level called §3{$args[1]}!");
						break;
					}
					if (!Server::getInstance()->isLevelLoaded($args[1])) {
						Server::getInstance()->loadLevel($args[1]);
						break;
					}
					if (!is_file($file = $this->getPlugin()->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $args[1] . ".yml")) {
						$config = new Config($file, Config::YAML);
						$config->reload();
						$config->save();
					}
					$this->getPlugin()->addArena($args[1]);
					$this->getPlugin()->setSetter($sender, $args[1]);
					$sender->sendMessage("§3You are now in setup mode.\n" .
						"§b§l» §7use §3help §7to display available commands\n" .
						"§b§l» §7or §3done §7to leave setup mode");
					break;
				case 'npc':
					foreach ($sender->getLevel()->getEntities() as $entity) {
						if ($entity instanceof JoinEntity) {
							$entity->close();
						}
					}
					$nbt = Entity::createBaseNBT($sender->asVector3());
					$nbt->setTag(clone $sender->namedtag->getCompoundTag('Skin'));
					$npc = new JoinEntity($sender->getLevel(), $nbt);
					$npc->spawnToAll();
					$sender->sendMessage("§a§l»§r§7 NPC Placed.");
					break;
				case "arenas":
					if (count($this->getPlugin()->arenas) === 0) {
						$sender->sendMessage("§a§l»§r§7 There are 0 arenas.");
						break;
					}
					$list = "§b§l» §r§7Arenas:\n";
					foreach ($this->getPlugin()->arenas as $name => $arena) {
						if ($arena->setup) {
							$list .= "§7- $name : §cdisabled : §3{$arena->gameType}\n";
						} else {
							$list .= "§7- $name : §aenabled : §3{$arena->gameType}\n";
						}
					}
					$sender->sendMessage($list);
					break;
				case 'remove':
					if (!isset($args[1])) {
						$sender->sendMessage("§c§l»§r§7 Usage: §7/wg remove <arenaName>");
						break;
					}
					if (!isset($this->getPlugin()->arenas[$args[1]])) {
						$sender->sendMessage("§c§l»§r§7 Arena $args[1] was not found!");
						break;
					}

					$arena = $this->getPlugin()->arenas[$args[1]];
					foreach ($arena->players as $player) {
						$player->teleport($this->getPlugin()->getServer()->getDefaultLevel()->getSpawnLocation());
					}

					if (is_file($file = $this->getPlugin()->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $args[1] . ".yml")) {
						unlink($file);
					}
					unset($this->getPlugin()->arenas[$args[1]]);

					$sender->sendMessage("§a§l»§r§7 Arena removed!");
					break;
				case 'tops':
					// TODO: tops

			}
		} else {
			$sender->sendMessage($this->getUsage());
		}

		return;
	}
}