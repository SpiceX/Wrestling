<?php

/**
 * Copyright 2020-2022 LiTEK - Josewowgame2888
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
namespace urbodus\wrestling\scoreboard;

use pocketmine\network\mcpe\protocol\{RemoveObjectivePacket, SetDisplayObjectivePacket, SetScorePacket};
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Player;
use urbodus\wrestling\Wrestling;

class Scoreboard
{


	public const ACTION_CREATE = 0;
	public const ACTION_MODIFY = 1;

	public const DISPLAY_MODE_LIST = "list";
	public const DISPLAY_MODE_SIDEBAR = "sidebar";

	public const SORT_ASCENDING = 0;
	public const SORT_DESCENDING = 1;


	public function __construct(Wrestling $plugin, string $title, int $action)
	{
		$this->plugin = $plugin;
		$this->displayName = $title;
		if ($action === self::ACTION_CREATE) {
			if ($this->plugin->getStore()->getId($title) === null) {
				$this->objectiveName = uniqid();
			} else {
				$this->objectiveName = $this->plugin->getStore()->getId($title);
				$this->displaySlot = $this->plugin->getStore()->getDisplaySlot($this->objectiveName);
				$this->sortOrder = $this->plugin->getStore()->getSortOrder($this->objectiveName);
				$this->scoreboardId = $this->plugin->getStore()->getScoreboardId($this->objectiveName);
			}
		} else {
			if ($this->plugin->getStore()->getId($title) !== null) {
				$this->objectiveName = $this->plugin->getStore()->getId($title);
				$this->displaySlot = $this->plugin->getStore()->getDisplaySlot($this->objectiveName);
				$this->sortOrder = $this->plugin->getStore()->getSortOrder($this->objectiveName);
				$this->scoreboardId = $this->plugin->getStore()->getScoreboardId($this->objectiveName);
			} else {
				$this->plugin->getLogger()->info("The scoreboard $title doesn't exist.");
			}
		}
	}

	const MAX_LINES = 15;

	/** @var Wrestling */
	private $plugin;

	/** @var string */
	private $objectiveName;

	/** @var string */
	private $displayName;

	/** @var string */
	private $displaySlot;

	/** @var int */
	private $sortOrder;

	/** @var int */
	private $scoreboardId;

	/** @var bool */
	private $padding;

	/**
	 * @param        $player
	 */

	public function showTo(Player $player): void
	{
		$pk = new SetDisplayObjectivePacket();
		$pk->displaySlot = $this->displaySlot;
		$pk->objectiveName = $this->objectiveName;
		$pk->displayName = $this->displayName;
		$pk->criteriaName = "dummy";
		$pk->sortOrder = $this->sortOrder;
		$player->sendDataPacket($pk);
		$this->plugin->getStore()->addViewer($this->objectiveName, $player->getName());
	}

	/**
	 * @param        $player
	 */

	public function hideFrom(Player $player): void
	{
		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $this->objectiveName;
		$player->sendDataPacket($pk);

		$this->plugin->getStore()->removeViewer($this->objectiveName, $player->getName());
	}

	/**
	 * @param int $line
	 * @param string $message
	 */

	public function setLine(int $line, string $message): void
	{
		if (!$this->plugin->getStore()->entryExist($this->objectiveName, ($line - 1)) && $line !== 1) {
			for ($i = 1; $i <= ($line - 1); $i++) {
				if (!$this->plugin->getStore()->entryExist($this->objectiveName, ($i - 1))) {
					$entry = new ScorePacketEntry();
					$entry->objectiveName = $this->objectiveName;
					$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
					$entry->customName = str_repeat(" ", $i);
					$entry->score = self::MAX_LINES - $i;
					$entry->scoreboardId = ($this->scoreboardId + $i);
					$this->plugin->getStore()->addEntry($this->objectiveName, ($i - 1), $entry);
				}
			}
		}

		$entry = new ScorePacketEntry();
		$entry->objectiveName = $this->objectiveName;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
		$this->padding ? $entry->customName = str_pad($message, ((strlen($this->displayName) * 2) - strlen($message))) : $entry->customName = $message;
		$entry->score = self::MAX_LINES - $line;
		$entry->scoreboardId = ($this->scoreboardId + $line);
		//$pk->entries[] = $entry;
		$this->plugin->getStore()->addEntry($this->objectiveName, ($line - 1), $entry);

		foreach ($this->plugin->getStore()->getViewers($this->objectiveName) as $name) {
			$p = $this->plugin->getServer()->getPlayer($name);
			if ($p !== null) {
				$pk = new SetScorePacket();
				$pk->type = SetScorePacket::TYPE_CHANGE;
				foreach ($this->plugin->getStore()->getEntries($this->objectiveName) as $index => $entry) {
					$pk->entries[$index] = $entry;
				}
				$p->sendDataPacket($pk);
			}
		}
	}

	/**
	 * @param int $line
	 */

	public function removeLine(int $line): void
	{
		$pk = new SetScorePacket();
		$pk->type = SetScorePacket::TYPE_REMOVE;

		$entry = new ScorePacketEntry();
		$entry->objectiveName = $this->objectiveName;
		$entry->score = self::MAX_LINES - $line;
		$entry->scoreboardId = ($this->scoreboardId + $line);
		$pk->entries[] = $entry;

		foreach ($this->plugin->getStore()->getViewers($this->objectiveName) as $name) {
			$p = $this->plugin->getServer()->getPlayer($name);
			$p->sendDataPacket($pk);
		}

		$this->plugin->getStore()->removeEntry($this->objectiveName, $line);
	}

	public function removeLines(): void
	{
		$pk = new SetScorePacket();
		$pk->type = SetScorePacket::TYPE_REMOVE;

		for ($line = 0; $line <= self::MAX_LINES; $line++) {
			$entry = new ScorePacketEntry();
			$entry->objectiveName = $this->objectiveName;
			$entry->score = $line;
			$entry->scoreboardId = ($this->scoreboardId + $line);
			$pk->entries[] = $entry;
		}

		foreach ($this->plugin->getStore()->getViewers($this->objectiveName) as $name) {
			$p = $this->plugin->getServer()->getPlayer($name);
			$p->sendDataPacket($pk);
		}

		$this->plugin->getStore()->removeEntries($this->objectiveName);
	}

	/**
	 * @param string $displaySlot
	 * @param int $sortOrder
	 * @param bool $padding
	 */

	public function create(string $displaySlot, int $sortOrder, bool $padding = true): void
	{
		$this->displaySlot = $displaySlot;
		$this->sortOrder = $sortOrder;
		$this->padding = $padding;
		$this->scoreboardId = mt_rand(1, 100000);
		$this->plugin->getStore()->registerScoreboard($this->objectiveName, $this->displayName, $this->displaySlot, $this->sortOrder, $this->scoreboardId);
	}

	public function delete(): void
	{
		$this->plugin->getStore()->unregisterScoreboard($this->objectiveName, $this->displayName);
	}

	/**
	 * @param string $oldName
	 * @param string $newName
	 */

	public function rename(string $oldName, string $newName): void
	{
		$this->displayName = $newName;

		$this->plugin->getStore()->rename($oldName, $newName);

		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $this->objectiveName;

		$pk2 = new SetDisplayObjectivePacket();
		$pk2->displaySlot = $this->displaySlot;
		$pk2->objectiveName = $this->objectiveName;
		$pk2->displayName = $this->displayName;
		$pk2->criteriaName = "dummy";
		$pk2->sortOrder = $this->sortOrder;

		$pk3 = new SetScorePacket();
		$pk3->type = SetScorePacket::TYPE_CHANGE;
		foreach ($this->plugin->getStore()->getEntries($this->objectiveName) as $index => $entry) {
			$pk3->entries[$index] = $entry;
		}

		foreach ($this->plugin->getStore()->getViewers($this->objectiveName) as $name) {
			$p = $this->plugin->getServer()->getPlayer($name);
			$p->sendDataPacket($pk);
			$p->sendDataPacket($pk2);
			$p->sendDataPacket($pk3);
		}
	}

	/**
	 * @return array
	 */

	public function getViewers(): array
	{
		return $this->plugin->getStore()->getViewers($this->objectiveName);
	}

	/**
	 * @return array
	 */

	public function getEntries(): array
	{
		return $this->plugin->getStore()->getEntries($this->objectiveName);
	}
}