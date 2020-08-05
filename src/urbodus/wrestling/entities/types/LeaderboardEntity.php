<?php


namespace urbodus\wrestling\entities\types;


use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use urbodus\wrestling\Wrestling;

class LeaderboardEntity extends Human
{

	/** @var string */
	private $gameType;

	public function __construct(Level $level, CompoundTag $nbt)
	{
		parent::__construct($level, $nbt);
		$this->setSkin(new Skin('Standard_Custom', str_repeat("\x00", 8192)));
		$this->sendSkin();
		$this->gameType = $nbt->getString("LeaderboardType");
	}

	public function entityBaseTick(int $tickDiff = 1): bool
	{
		$this->setNameTag($this->getLeaderboardText());
		return parent::entityBaseTick($tickDiff);
	}

	private function getLeaderboardText(): string
	{
		return Wrestling::getInstance()->getSqliteProvider()->getGlobalTops($this->gameType);
	}
}