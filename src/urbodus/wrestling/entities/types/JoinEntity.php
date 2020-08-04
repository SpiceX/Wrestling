<?php


namespace urbodus\wrestling\entities\types;

use pocketmine\entity\Human;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use urbodus\wrestling\Wrestling;

class JoinEntity extends Human
{

	/**
	 * MainEntity constructor.
	 * @param Level $level
	 * @param CompoundTag $nbt
	 */
	public function __construct(Level $level, CompoundTag $nbt)
	{
		parent::__construct($level, $nbt);
		$this->setNameTagAlwaysVisible(true);
		$this->setNameTagVisible(true);
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return '';
	}

	/**
	 * @param int $currentTick
	 * @return bool
	 */
	public function onUpdate(int $currentTick): bool
	{
		if ($this->getScale() != 1.2) {
			$this->setScale(1.2);
		}
		$this->setNameTag("§l§b» §7CLICK TO JOIN §l§b«" . "\n" . $this->getRandomGame() . " §r§8[§a1.0.0§8]" . "\n§3Playing: §7" . $this->getPlaying());
		return parent::onUpdate($currentTick);
	}

	/**
	 * @return int
	 */
	private function getPlaying(): int
	{
		$count = 0;
		foreach (Wrestling::getInstance()->arenas as $name => $arena) {
			foreach ($arena->players as $player) {
				$count++;
			}
		}
		return $count;
	}

	private function getRandomGame(){
		$games = ["§9§lBUHC", "§9§lSUMO", "§l§91VS1"];
		return $games[array_rand($games)];
	}
}