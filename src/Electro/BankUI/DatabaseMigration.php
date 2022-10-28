<?php

namespace Electro\BankUI;

use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;

class DatabaseMigration
{
    public $plugin;
    private static $instance;

    public function __construct(BankUI $plugin){
        $this->plugin = $plugin;
    }

    // Todo: Use multiple databases at the same time.
    public function migrateDatabase(string $migrateFrom, string $migrateTo, Player $player = null)
    {
        // Supported Conversions: YAML, SQL, SQLite, MySQL

        $migrateTo = strtolower($migrateTo);
        $migrateFrom = strtolower($migrateFrom);
        $supportedDatabases = ["sql", "sqlite", "mysql"];

        $currentDBType = $this->plugin->getConfig()->getNested("database.type");

        if ($migrateFrom == "sql")
        {
            if ($currentDBType == "mysql") $migrateFrom = "mysql";
            else $migrateFrom = "sqlite";
        }
        if ($migrateTo == "sql")
        {
            if ($currentDBType == "mysql") $migrateTo = "sqlite";
            else $migrateTo = "mysql";
        }

        if ($migrateFrom == "yaml" && $migrateTo == "sql")
        {
            if (!file_exists($this->plugin->getDataFolder() . "Players")) return false;
            $this->plugin->getLogger()->warning("Your database is being migrated to our new database system, please do not restart your server until this finishes.");
            foreach (glob($this->plugin->getDataFolder() . "Players/*.yml") as $players)
            {
                $playerYAMLBank = new Config($players);
                $transactions = $playerYAMLBank->get('Transactions');
                if (empty($transactions) || $transactions == 0) $transactions = "";
                else if (is_array($transactions)) $transactions = implode("\n",$transactions);
                $this->plugin->createAccount(pathinfo($players)['filename'], $playerYAMLBank->get('Money'), $transactions);
                unlink($players);
            }
            rmdir($this->plugin->getDataFolder() . "Players");
            $this->plugin->getLogger()->warning("Database migration has been complete.");
            return true;
        }
        else if (!in_array($migrateFrom, $supportedDatabases) || !in_array($migrateTo, $supportedDatabases))
        {
            throw new \InvalidArgumentException("You can not migrate from and to these databases. Make sure they are either SQL, MySQL, or SQLite");
        }
        else if ($migrateFrom == $migrateTo)
        {
            throw new \InvalidArgumentException("You can not migrate a database to the same database");
        }
        else
        {
            $this->plugin->getLogger()->warning("Your database is being migrated to " . $migrateTo . ", please do not restart your server until this finishes.");
            if (!is_null($player) && $this->plugin->getServer()->getPlayerExact($player->getName()))
            {
                $player->sendMessage("§l§aStarted Migration: §r§eYour database is being migrated to " . $migrateTo . ", please do not restart your server until this finishes.");
            }
            $config = $this->plugin->getConfig();

            $this->plugin->database->close();

            try
            {
                if ($migrateFrom == "mysql")
                {
                    $this->plugin->database = libasynql::create($this->plugin, ["type" => "mysql", "mysql" => ["host" => $config->getNested("database.mysql.host"), "username" => $config->getNested("database.mysql.username"), "password" => $config->getNested("database.mysql.password"), "schema" => $config->getNested("database.mysql.schema")], $config->getNested("database.worker-limit")],[
                        "sqlite" => "sqlite.sql",
                        "mysql" => "mysql.sql"
                    ]);
                }
                else
                {
                    $this->plugin->database = libasynql::create($this->plugin, ["type" => "sqlite", "sqlite" => ["file" => $config->getNested("database.sqlite.file")], $config->getNested("database.worker-limit")],[
                        "sqlite" => "sqlite.sql",
                        "mysql" => "mysql.sql"
                    ]);
                }
            }
            catch (SqlError $e)
            {
                $this->plugin->database = libasynql::create($this->plugin, $this->plugin->getConfig()->get("database"), [
                    "sqlite" => "sqlite.sql",
                    "mysql" => "mysql.sql"
                ]);
                $this->plugin->getLogger()->critical("An error occurred while migrating your database. Make sure the SQLite and MysQL database is setup correctly!");
                if (!is_null($player) && $this->plugin->getServer()->getPlayerExact($player->getName()))
                {
                    $player->sendMessage("§l§aFailed Migration: §r§eAn error occurred while migrating your database. Make sure the SQLite and MysQL database is setup correctly!");
                }
                return false;
            }



            $this->plugin->database->executeSelect("init.table.getall", [], function(array $rows) use ($config, $migrateTo, $player){
                $this->plugin->database->close();

                try
                {
                    if ($migrateTo == "mysql")
                    {
                        $this->plugin->database = libasynql::create($this->plugin, ["type" => "mysql", "mysql" => ["host" => $config->getNested("database.mysql.host"), "username" => $config->getNested("database.mysql.username"), "password" => $config->getNested("database.mysql.password"), "schema" => $config->getNested("database.mysql.schema")], $config->getNested("database.worker-limit")],[
                            "sqlite" => "sqlite.sql",
                            "mysql" => "mysql.sql"
                        ]);
                    }
                    else
                    {
                        $this->plugin->database = libasynql::create($this->plugin, ["type" => "sqlite", "sqlite" => ["file" => $config->getNested("database.sqlite.file")], $config->getNested("database.worker-limit")],[
                            "sqlite" => "sqlite.sql",
                            "mysql" => "mysql.sql"
                        ]);
                    }
                }
                catch (SqlError $e)
                {
                    $this->plugin->database = libasynql::create($this->plugin, $this->plugin->getConfig()->get("database"), [
                        "sqlite" => "sqlite.sql",
                        "mysql" => "mysql.sql"
                    ]);
                    $this->plugin->getLogger()->critical("An error occurred while migrating your database. Make sure the SQLite and MysQL database is setup correctly!");
                    if (!is_null($player) && $this->plugin->getServer()->getPlayerExact($player->getName()))
                    {
                        $player->sendMessage("§l§cFailed Migration: §r§eAn error occurred while migrating your database. Make sure the SQLite and MysQL database is setup correctly!");
                    }
                    return false;
                }
                $this->plugin->database->executeGeneric("init.table.drop");
                $this->plugin->database->executeGeneric("init.table.creation");
                foreach ($rows as $row)
                {
                    //echo (var_dump($row));
                    $this->plugin->createAccount($row["Player"], $row["Money"], $row["Transactions"]);
                }
                $this->plugin->database->close();

                $this->plugin->database = libasynql::create($this->plugin, $this->plugin->getConfig()->get("database"), [
                    "sqlite" => "sqlite.sql",
                    "mysql" => "mysql.sql"
                ]);
                $this->plugin->getLogger()->warning("Database migration has been complete!");
                if (!is_null($player) && $this->plugin->getServer()->getPlayerExact($player->getName()))
                {
                    $player->sendMessage("§l§aSuccessful Migration: §r§eDatabase migration has been complete!");
                }
            });
        }
    }

    public static function getInstance(): self {
        return self::$instance ??= new self(BankUI::getInstance());
    }
}
