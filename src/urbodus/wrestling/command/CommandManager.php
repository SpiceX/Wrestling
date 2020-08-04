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
namespace urbodus\wrestling\command;


use pocketmine\command\Command;
use pocketmine\command\types\ArenaSetupCommand;
use pocketmine\plugin\PluginException;
use urbodus\wrestling\Wrestling;

class CommandManager
{

	/** @var Wrestling */
	private $plugin;

	/**
	 * CommandManager constructor.
	 *
	 * @param Wrestling $plugin
	 */
	public function __construct(Wrestling $plugin)
	{
		$this->registerCommand(new ArenaSetupCommand("wg", "wrestling setup command", "/wg help", ["wg"]));
		$this->registerCommand(new ArenaSetupCommand("wrestling", "wrestling user command", "/wrestling help", ["wrestling"]));
		$this->plugin = $plugin;
	}

	/**
	 * @param Command $command
	 */
	public function registerCommand(Command $command): void
	{
		$commandMap = $this->plugin->getServer()->getCommandMap();
		$existingCommand = $commandMap->getCommand($command->getName());
		if ($existingCommand !== null) {
			$commandMap->unregister($existingCommand);
		}
		$commandMap->register($command->getName(), $command);
	}

	/**
	 * @param string $name
	 */
	public function unregisterCommand(string $name): void
	{
		$commandMap = $this->plugin->getServer()->getCommandMap();
		$command = $commandMap->getCommand($name);
		if ($command === null) {
			throw new PluginException("Invalid command: $name to un-register.");
		}
		$commandMap->unregister($commandMap->getCommand($name));
	}
}