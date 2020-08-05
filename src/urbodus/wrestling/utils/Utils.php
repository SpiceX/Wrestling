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
namespace urbodus\wrestling\utils;


use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;

final class Utils
{
	public static function addGuillemets(string $message): string
	{
		return "§l§b» $message §l§b«";
	}

	public static function playSound(Player $player, string $sound): void
	{
		$pk = new PlaySoundPacket();
		$pk->soundName = $sound;
		$pk->x = (int)$player->x;
		$pk->y = (int)$player->y;
		$pk->z = (int)$player->z;
		$pk->volume = 1;
		$pk->pitch = 1;
		$player->dataPacket($pk);
	}
}