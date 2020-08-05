<?php


namespace urbodus\wrestling\provider;

use pocketmine\Player;
use SQLite3;
use urbodus\wrestling\arena\Game;
use urbodus\wrestling\utils\Utils;
use urbodus\wrestling\Wrestling;

class SQLite3Provider
{
	/** @var SQLite3 */
	private $db;

	/**
	 * Connection constructor.
	 * @param Wrestling $plugin
	 */
	public function __construct(Wrestling $plugin)
	{
		$this->db = new SQLite3($plugin->getDataFolder() . DIRECTORY_SEPARATOR . "database.sq3");
		$this->createTables();
	}

	/**
	 * @query create table of Games
	 */
	protected function createTables()
	{
		$this->db->exec('CREATE TABLE IF NOT EXISTS Sumo (
            NAME TEXT NOT NULL,
            WINS INT NOT NULL,
            LOSSES INT NOT NULL,
            UNIQUE(NAME)
        )');
		$this->db->exec('CREATE TABLE IF NOT EXISTS OneVsOne (
            NAME TEXT NOT NULL,
            WINS INT NOT NULL,
            LOSSES INT NOT NULL,
            UNIQUE(NAME)
        )');
		$this->db->exec('CREATE TABLE IF NOT EXISTS BUHC (
            NAME TEXT NOT NULL,
            WINS INT NOT NULL,
            LOSSES INT NOT NULL,
            UNIQUE(NAME)
        )');
	}

	/**
	 * @return SQLite3
	 */
	public function getDatabase(): SQLite3
	{
		return $this->db;
	}

	/**
	 * @param Player $player
	 */
	public function addNewPlayer(Player $player)
	{
		$player->sendMessage("§b§l» §r§7Hey, {$player->getName()}, is your first game!");
		$player->sendMessage("§9§l» §r§7We are adding you to the database to follow your progress in your battles...");
		$query2 = $this->db->prepare("INSERT OR IGNORE INTO Sumo(NAME,WINS,LOSSES) SELECT :name, :wins, :losses WHERE NOT EXISTS(SELECT * FROM Sumo WHERE NAME = :name);");
		$query3 = $this->db->prepare("INSERT OR IGNORE INTO OneVsOne(NAME,WINS,LOSSES) SELECT :name, :wins, :losses WHERE NOT EXISTS(SELECT * FROM OneVsOne WHERE NAME = :name);");
		$query4 = $this->db->prepare("INSERT OR IGNORE INTO BUHC(NAME,WINS,LOSSES) SELECT :name, :wins, :losses WHERE NOT EXISTS(SELECT * FROM BUHC WHERE NAME = :name);");
		$query2->bindValue(":name", $player->getName(), SQLITE3_TEXT);
		$query2->bindValue(":wins", 0, SQLITE3_NUM);
		$query2->bindValue(":losses", 0, SQLITE3_NUM);
		$query3->bindValue(":name", $player->getName(), SQLITE3_TEXT);
		$query3->bindValue(":wins", 0, SQLITE3_NUM);
		$query3->bindValue(":losses", 0, SQLITE3_NUM);
		$query4->bindValue(":name", $player->getName(), SQLITE3_TEXT);
		$query4->bindValue(":wins", 0, SQLITE3_NUM);
		$query4->bindValue(":losses", 0, SQLITE3_NUM);
		$query2->execute();
		$query3->execute();
		$query4->execute();
	}

	/**
	 * Close database
	 */
	public function closeDatabase()
	{
		$this->db->close();
	}

	/**
	 * @param Player $player
	 * @return string
	 */
	public function getScore(Player $player): string
	{
		$summary = $this->getSummary($player);
		return Utils::addGuillemets("§r§bWrestling Summary Score") . "\n§r".
			"§9Player " . "§7: " . "{$player->getName()}\n" .
			"§3BUHC Wins " . "§7: " . "§7{$summary['buhc']['wins']}\n" .
			"§3SUMO Wins " . "§7: " . "§7{$summary['sumo']['wins']}\n" .
			"§31vs1 Wins " . "§7: " . "§7{$summary['1vs1']['wins']}\n" .
			"§6--------------------" . "\n" .
			"§3BUHC Losses " . "§7: " . "§7{$summary['buhc']['losses']}\n" .
			"§3SUMO Losses " . "§7: " . "§7{$summary['sumo']['losses']}\n" .
			"§31vs1 Losses " . "§7: " . "§7{$summary['1vs1']['losses']}\n" .
			"§6--------------------";
	}

	/**
	 * @param Player $player
	 * @return array[]
	 */
	public function getSummary(Player $player): array
	{
		return [
			'buhc' => ['wins' => $this->getWins($player, Game::BUHC), 'losses' => $this->getLosses($player, Game::BUHC)],
			'sumo' => ['wins' => $this->getWins($player, Game::SUMO), 'losses' => $this->getLosses($player, Game::SUMO)],
			'1vs1' => ['wins' => $this->getWins($player, Game::ONEVSONE), 'losses' => $this->getLosses($player, Game::ONEVSONE)],
		];
	}

	/**
	 * @param Player $player
	 * @param string $gameType
	 * @return int
	 */
	public function getWins(Player $player, string $gameType): int
	{
		$name = $player->getName();
		switch ($gameType) {
			case Game::BUHC:
				return $this->db->querySingle("SELECT WINS FROM BUHC WHERE NAME = '$name'");
				break;
			case Game::ONEVSONE:
				return $this->db->querySingle("SELECT WINS FROM OneVsOne WHERE NAME = '$name'");
				break;
			case Game::SUMO:
				return $this->db->querySingle("SELECT WINS FROM Sumo WHERE NAME = '$name'");
				break;
		}
		return 0;
	}

	/**
	 * @param Player $player
	 * @param string $gameType
	 * @return int
	 */
	public function getLosses(Player $player, string $gameType): int
	{
		$name = $player->getName();
		switch ($gameType) {
			case Game::BUHC:
				return (int)$this->db->querySingle("SELECT LOSSES FROM BUHC WHERE NAME = '$name'");
				break;
			case Game::ONEVSONE:
				return (int)$this->db->querySingle("SELECT LOSSES FROM OneVsOne WHERE NAME = '$name'");
				break;
			case Game::SUMO:
				return (int)$this->db->querySingle("SELECT LOSSES FROM Sumo WHERE NAME = '$name'");
				break;
		}
		return 0;
	}

	/**
	 * @param Player $player
	 * @return bool
	 */
	public function verifyPlayerInDB(Player $player): bool
	{
		$name = $player->getName();
		$query = $this->db->querySingle("SELECT NAME FROM BUHC WHERE NAME = '$name'");
		if ($query === null) {
			return false;
		}
		return true;
	}

	/**
	 * Configure leaderboard
	 * @param string $gameType
	 * @return string
	 */
	public function getGlobalTops(string $gameType): string
	{
		$leaderboard = [];
		$result = null;
		switch ($gameType) {
			case Game::SUMO:
				$result = $this->db->query("SELECT NAME, WINS FROM Sumo ORDER BY WINS DESC LIMIT 10");
				break;
			case Game::ONEVSONE:
				$result = $this->db->query("SELECT NAME, WINS FROM OneVsOne ORDER BY WINS DESC LIMIT 10");
				break;
			case Game::BUHC:
				$result = $this->db->query("SELECT NAME, WINS FROM BUHC ORDER BY WINS DESC LIMIT 10");
				break;
		}
		if ($result === null) {
			return '';
		}
		$index = 0;
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$leaderboard[$index++] = $row;
		}
		$count = count($leaderboard);
		$break = "\n";
		if ($count > 0) {
			$top1 = "§e1. §6Name: §a" . $leaderboard[0]['NAME'] . "  §6Wins: §a" . $leaderboard[0]['WINS'];
		} else {
			$top1 = '';
		}
		if ($count > 1) {
			$top2 = "§e2. §6Name: §e" . $leaderboard[1]['NAME'] . "  §6Wins: §e" . $leaderboard[1]['WINS'];
		} else {
			$top2 = '';
		}
		if ($count > 2) {
			$top3 = "§e3. §6Name: §e" . $leaderboard[2]['NAME'] . "  §6Wins: §e" . $leaderboard[2]['WINS'];
		} else {
			$top3 = '';
		}
		if ($count > 3) {
			$top4 = "§e4. §6Name: §e" . $leaderboard[3]['NAME'] . "  §6Wins: §e" . $leaderboard[3]['WINS'];
		} else {
			$top4 = '';
		}
		if ($count > 4) {
			$top5 = "§e5. §6Name: §e" . $leaderboard[4]['NAME'] . "  §6Wins: §e" . $leaderboard[4]['WINS'];
		} else {
			$top5 = '';
		}
		if ($count > 5) {
			$top6 = "§e6. §6Name: §e" . $leaderboard[5]['NAME'] . "  §6Wins: §e" . $leaderboard[5]['WINS'];
		} else {
			$top6 = '';
		}
		if ($count > 6) {
			$top7 = "§e7. §6Name: §e" . $leaderboard[6]['NAME'] . "  §6Wins: §e" . $leaderboard[6]['WINS'];
		} else {
			$top7 = '';
		}
		if ($count > 7) {
			$top8 = "§e8. §6Name: §e" . $leaderboard[7]['NAME'] . "  §6Wins: §e" . $leaderboard[7]['WINS'];
		} else {
			$top8 = '';
		}
		if ($count > 8) {
			$top9 = "§e9. §6Name: §e" . $leaderboard[8]['NAME'] . "  §6Wins: §e" . $leaderboard[8]['WINS'];
		} else {
			$top9 = '';
		}
		if ($count > 9) {
			$top10 = "§e10. §6Name: §e" . $leaderboard[9]['NAME'] . "  §6Wins: §e" . $leaderboard[9]['WINS'];
		} else {
			$top10 = '';
		}
		return Utils::addGuillemets("§bWrestling Leaderboard") . "\n" . "§9" . strtoupper($gameType) . "\n" . $top1 . $break . $top2 . $break . $top3 . $break . $top4 . $break . $top5 . $break . $top6 . $break . $top7 . $break . $top8 . $break . $top9 . $break . $top10;
	}

	/**
	 * @param Player $player
	 * @param string $gametype
	 */
	public function addWin(Player $player, string $gametype)
	{
		$name = $player->getName();
		switch ($gametype){
			case Game::BUHC:
				$result = $this->getWins($player, $gametype) + 1;
				$this->db->exec("UPDATE `BUHC` SET `WINS`='$result' WHERE NAME='$name';");
				break;
			case Game::SUMO:
				$result = $this->getWins($player, $gametype) + 1;
				$this->db->exec("UPDATE `SUMO` SET `WINS`='$result' WHERE NAME='$name';");
				break;
			case Game::ONEVSONE:
				$result = $this->getWins($player, $gametype) + 1;
				$this->db->exec("UPDATE `OneVsOne` SET `WINS`='$result' WHERE NAME='$name';");
				break;
		}
	}

	/**
	 * @param Player $player
	 * @param string $gametype
	 */
	public function addLose(Player $player, string $gametype)
	{
		$name = $player->getName();
		switch ($gametype){
			case Game::BUHC:
				$result = $this->getLosses($player, $gametype) + 1;
				$this->db->exec("UPDATE `BUHC` SET `LOSSES`='$result' WHERE NAME='$name';");
				break;
			case Game::SUMO:
				$result = $this->getLosses($player, $gametype) + 1;
				$this->db->exec("UPDATE `SUMO` SET `LOSSES`='$result' WHERE NAME='$name';");
				break;
			case Game::ONEVSONE:
				$result = $this->getLosses($player, $gametype) + 1;
				$this->db->exec("UPDATE `OneVsOne` SET `LOSSES`='$result' WHERE NAME='$name';");
				break;
		}
	}

}