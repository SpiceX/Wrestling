<?php


namespace urbodus\wrestling\provider;

use pocketmine\Player;
use SQLite3;
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
	 * @return SQLite3
	 */
	public function getDatabase(): SQLite3
	{
		return $this->db;
	}

	/**
	 * @query create table of Players
	 */
	protected function createTables()
	{
		$this->db->exec('CREATE TABLE IF NOT EXISTS Players (
            NAME TEXT NOT NULL,
            KILLS INT NOT NULL,
            DEATHS INT NOT NULL,
            KDR FLOAT NOT NULL,
            UNIQUE(NAME)
        )');
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

	public function addNewPlayer(Player $player){
		$query1 = $this->db->prepare("INSERT OR IGNORE INTO Players(NAME,KILLS,DEATHS,KDR) SELECT :name, :kills, :deaths, :kdr WHERE NOT EXISTS(SELECT * FROM Players WHERE NAME = :name);");
		$query2 = $this->db->prepare("INSERT OR IGNORE INTO Sumo(NAME,WINS,LOSSES) SELECT :name, :wins, :losses WHERE NOT EXISTS(SELECT * FROM Sumo WHERE NAME = :name);");
		$query3 = $this->db->prepare("INSERT OR IGNORE INTO OneVsOne(NAME,WINS,LOSSES) SELECT :name, :wins, :losses WHERE NOT EXISTS(SELECT * FROM OneVsOne WHERE NAME = :name);");
		$query4 = $this->db->prepare("INSERT OR IGNORE INTO BUHC(NAME,WINS,LOSSES) SELECT :name, :wins, :losses WHERE NOT EXISTS(SELECT * FROM BUHC WHERE NAME = :name);");
		$query1->bindValue(":name", $player->getName(), SQLITE3_TEXT);
		$query1->bindValue(":kills", 0, SQLITE3_NUM);
		$query1->bindValue(":deaths", 0, SQLITE3_NUM);
		$query1->bindValue(":kdr", 0, SQLITE3_FLOAT);
		$query2->bindValue(":name", $player->getName(), SQLITE3_TEXT);
		$query2->bindValue(":wins", 0, SQLITE3_NUM);
		$query2->bindValue(":losses", 0, SQLITE3_NUM);
		$query3->bindValue(":name", $player->getName(), SQLITE3_TEXT);
		$query3->bindValue(":wins", 0, SQLITE3_NUM);
		$query3->bindValue(":losses", 0, SQLITE3_NUM);
		$query4->bindValue(":name", $player->getName(), SQLITE3_TEXT);
		$query4->bindValue(":wins", 0, SQLITE3_NUM);
		$query4->bindValue(":losses", 0, SQLITE3_NUM);
		$query1->execute();
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
	 * @param string $player
	 * @return mixed
	 */
	public function getKills(string $player)
	{
		return $this->db->querySingle("SELECT KILLS FROM Players WHERE NAME = '$player'");
	}

	/**
	 * @param string $player
	 * @return mixed
	 */
	public function getDeaths(string $player)
	{
		return $this->db->querySingle("SELECT DEATHS FROM Players WHERE NAME = '$player'");
	}

	/**
	 * @param string $player
	 * @return float|int
	 */
	public function getKDR(string $player)
	{
		if ($this->getDeaths($player) == 0) {
			return 0;
		}
		return $this->getKills($player) / $this->getDeaths($player);
	}

	/**
	 * @param string $player
	 */
	public function addKill(string $player)
	{
		$kills = $this->getKills($player);
		$result = $kills + 1;
		$this->db->exec("UPDATE Players SET KILLS='$result' WHERE NAME='$player'");
		$kdr = $this->getKDR($player);
		$this->db->exec("UPDATE Players SET KDR='$kdr' WHERE NAME='$player'");
	}

	/**
	 * @param string $player
	 */
	public function addDeath(string $player)
	{
		$deaths = $this->getDeaths($player);
		$result = $deaths + 1;
		$this->db->exec("UPDATE Players SET DEATHS='$result' WHERE NAME='$player'");
		$kdr = $this->getKDR($player);
		$this->db->exec("UPDATE Players SET KDR='$kdr' WHERE NAME='$player'");
	}

	/**
	 * @param string $player
	 * @return string
	 */
	public function getScore(string $player)
	{
		$kills = $this->getKills($player);
		$deaths = $this->getDeaths($player);
		$kdr = $this->getKDR($player);
		return "§7-§4--§7- " . "WRESTLING" . " §7-§4--§7-\n" .
			"§aPlayer " . "§6: " . "$player\n" .
			"§aKills " . "§6: " . "§7$kills\n" .
			"§aDeaths " . "§6: " . "§7$deaths\n" .
			"§aKill ratio " . "§6: " . "§7$kdr\n" .
			"§4--------------------";
	}

	/**
	 * @param string $player
	 * @return bool
	 */
	public function verifyPlayerInDB(string $player): bool
	{
		$query = $this->db->querySingle("SELECT NAME FROM Players WHERE NAME = '$player'");
		if ($query === null) {
			return false;
		}
		return true;
	}


	/**
	 * Configure leaderboard
	 */

	public function getTops()
	{
		$leaderboard = [];
		$result = $this->db->query("SELECT NAME, KILLS FROM Players ORDER BY KILLS DESC LIMIT 10");
		$index = 0;
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$leaderboard[$index++] = $row;
		}
		$count = count($leaderboard);
		$break = "\n";
		if ($count > 0) {
			$top1 = "§e1. §6Name: §a" . $leaderboard[0]['NAME'] . "  §6Kills: §a" . $leaderboard[0]['KILLS'];
		} else {
			$top1 = '';
		}
		if ($count > 1) {
			$top2 = "§e2. §6Name: §e" . $leaderboard[1]['NAME'] . "  §6Kills: §e" . $leaderboard[1]['KILLS'];
		} else {
			$top2 = '';
		}
		if ($count > 2) {
			$top3 = "§e3. §6Name: §e" . $leaderboard[2]['NAME'] . "  §6Kills: §e" . $leaderboard[2]['KILLS'];
		} else {
			$top3 = '';
		}
		if ($count > 3) {
			$top4 = "§e4. §6Name: §e" . $leaderboard[3]['NAME'] . "  §6Kills: §e" . $leaderboard[3]['KILLS'];
		} else {
			$top4 = '';
		}
		if ($count > 4) {
			$top5 = "§e5. §6Name: §e" . $leaderboard[4]['NAME'] . "  §6Kills: §e" . $leaderboard[4]['KILLS'];
		} else {
			$top5 = '';
		}
		if ($count > 5) {
			$top6 = "§e6. §6Name: §e" . $leaderboard[5]['NAME'] . "  §6Kills: §e" . $leaderboard[5]['KILLS'];
		} else {
			$top6 = '';
		}
		if ($count > 6) {
			$top7 = "§e7. §6Name: §e" . $leaderboard[6]['NAME'] . "  §6Kills: §e" . $leaderboard[6]['KILLS'];
		} else {
			$top7 = '';
		}
		if ($count > 7) {
			$top8 = "§e8. §6Name: §e" . $leaderboard[7]['NAME'] . "  §6Kills: §e" . $leaderboard[7]['KILLS'];
		} else {
			$top8 = '';
		}
		if ($count > 8) {
			$top9 = "§e9. §6Name: §e" . $leaderboard[8]['NAME'] . "  §6Kills: §e" . $leaderboard[8]['KILLS'];
		} else {
			$top9 = '';
		}
		if ($count > 9) {
			$top10 = "§e10. §6Name: §e" . $leaderboard[9]['NAME'] . "  §6Kills: §e" . $leaderboard[9]['KILLS'];
		} else {
			$top10 = '';
		}
		return "§4-----☣§cWrestling Stats§4☣-----\n" . "§7Top Kills\n" . $top1 . $break . $top2 . $break . $top3 . $break . $top4 . $break . $top5 . $break . $top6 . $break . $top7 . $break . $top8 . $break . $top9 . $break . $top10;
	}

	/**
	 * @return string
	 */
	public function getTopOne(): string
	{
		$leaderboard = [];
		$result = $this->db->query("SELECT NAME, KILLS FROM Players ORDER BY KILLS DESC LIMIT 10");
		$number = 0;
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$leaderboard[$number++] = $row;
		}
		$count = count($leaderboard);
		if ($count > 0) {
			return $leaderboard[0]['NAME'];
		}
		return '';
	}

}