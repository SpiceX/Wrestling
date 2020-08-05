<?php

/**
 * Copyright 2020-2022 LiTEK - Josewowgame
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

namespace urbodus\wrestling\form;

use pocketmine\Player;
use urbodus\wrestling\arena\Game;
use urbodus\wrestling\form\elements\Button;
use urbodus\wrestling\form\elements\Image;
use urbodus\wrestling\form\types\MenuForm;
use urbodus\wrestling\utils\Utils;
use urbodus\wrestling\Wrestling;

class FormManager
{
	/**
	 * @var Wrestling
	 */
	private $plugin;

	public function __construct(Wrestling $plugin)
	{
		$this->plugin = $plugin;
	}

	public function sendGamePanel(Player $player)
	{
		Utils::playSound($player, "random.pop2");
		$player->sendForm(new MenuForm(Utils::addGuillemets("§b§lWrestling Panel"), "§7Select an option: ",
			[
				new Button("§9Random Game\n§7Join random game.", new Image("textures/ui/icon_random", Image::TYPE_PATH)),
				new Button("§9BUHC\n§7Classic build practice", new Image("textures/ui/Scaffolding", Image::TYPE_PATH)),
				new Button("§9SUMO\n§7Dont fall from platform.", new Image("textures/ui/icon_fall", Image::TYPE_PATH)),
				new Button("§91VS1\n§7Beat your oponent.", new Image("textures/ui/resistance_effect", Image::TYPE_PATH)),
			], function (Player $player, Button $selected): void {
				switch ($selected->getValue()) {
					case 0:
						$arena = $this->plugin->getRandomArena(null);
						if ($arena != null) {
							$arena->joinToArena($player);
						} else {
							Utils::playSound($player, "note.bassattack");
							$player->sendMessage("§c§l» §r§7There is not available arenas!");
						}
						break;
					case 1:
						$arena = $this->plugin->getRandomArena(Game::BUHC);
						if ($arena != null) {
							$arena->joinToArena($player);
						} else {
							Utils::playSound($player, "note.bassattack");
							$player->sendMessage("§c§l» §r§7There is not available arenas!");
						}
						break;
					case 2:
						$arena = $this->plugin->getRandomArena(Game::SUMO);
						if ($arena != null) {
							$arena->joinToArena($player);
						} else {
							Utils::playSound($player, "note.bassattack");
							$player->sendMessage("§c§l» §r§7There is not available arenas!");
						}
						break;
					case 3:
						$arena = $this->plugin->getRandomArena(Game::ONEVSONE);
						if ($arena != null) {
							$arena->joinToArena($player);
						} else {
							Utils::playSound($player, "note.bassattack");
							$player->sendMessage("§c§l» §r§7There is not available arenas!");
						}
						break;
				}
			}));
	}

}