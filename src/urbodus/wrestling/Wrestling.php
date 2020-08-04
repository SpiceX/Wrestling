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
namespace urbodus\wrestling;

use Exception;
use pocketmine\plugin\PluginBase;
use urbodus\wrestling\arena\Arena;
use urbodus\wrestling\command\CommandManager;
use urbodus\wrestling\scoreboard\ScoreboardStore;

class Wrestling extends PluginBase
{
	/** @var Wrestling */
	private static $instance;

	/** @var Arena[] */
	public $arenas = [];

	/** @var ScoreboardStore */
	private $scoreboardStore;

	/** @var CommandManager */
	private $commandManager;

	public function onEnable()
	{
		$this->initVariables();
	}

	private function initVariables(){
		try {
			self::$instance = $this;
			$this->scoreboardStore = new ScoreboardStore();
			$this->commandManager = new CommandManager($this);
		} catch (Exception $exception){
			$this->getLogger()->alert("Some variables could not be loaded: {$exception->getMessage()}");
		}
	}

	/**
	 * @return ScoreboardStore
	 */
	public function getStore(): ScoreboardStore
	{
		return $this->scoreboardStore;
	}

	/**
	 * @return Wrestling
	 */
	public static function getInstance(): Wrestling
	{
		return self::$instance;
	}

	/**
	 * @return CommandManager
	 */
	public function getCommandManager(): CommandManager
	{
		return $this->commandManager;
	}

}