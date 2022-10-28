<?php

namespace Electro\BankUI;

use pocketmine\scheduler\ClosureTask;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;

class BackupSystem
{
    public $plugin;
    private static $instance;

    public function __construct(BankUI $plugin){
        $this->plugin = $plugin;
    }

    public function backupData(string $player = null)
    {
        if (!$this->plugin->backupsEnabled) throw new \InvalidArgumentException("Backups must be enabled to backup data!");
        $this->plugin->saveAllData();

        $this->plugin->database->executeSelect("init.table.getall", [], function(array $rows){

            $this->plugin->backupDatabase->executeGeneric("init.table.drop");
            $this->plugin->backupDatabase->executeGeneric("init.table.creation");
            foreach ($rows as $row)
            {
                $this->plugin->backupDatabase->executeInsert("init.table.createaccount", ["playername" => $row["Player"], "money" => $row["Money"], "transactions" => $row["Transactions"]]);
            }
        });
        if (is_null($player)) $this->plugin->getLogger()->info("Your server has been backed up automatically.");
        else $this->plugin->getLogger()->info($player ." has backed up the server.");
    }

    public function saveRestore(string $player = null)
    {
        if (!$this->plugin->backupsEnabled) throw new \InvalidArgumentException("Backups must be enabled to save a restore point!");
        $this->plugin->saveAllData();

        try
        {
            $this->plugin->restoreDatabase = libasynql::create($this->plugin, ["type" => "sqlite", "sqlite" => ["file" => "restore.sqlite"], 1],[
                "sqlite" => "sqlite.sql",
                "mysql" => "mysql.sql"
            ]);

            $this->plugin->database->executeSelect("init.table.getall", [], function(array $rows){

                $this->plugin->restoreDatabase->executeGeneric("init.table.drop");
                $this->plugin->restoreDatabase->executeGeneric("init.table.creation");
                foreach ($rows as $row)
                {
                    $this->plugin->restoreDatabase->executeInsert("init.table.createaccount", ["playername" => $row["Player"], "money" => $row["Money"], "transactions" => $row["Transactions"]]);
                }
            });
            // Todo: Closing Database Causes A Query Invalid Error
            //$this->restoreDatabase->close();
            $this->plugin->getLogger()->info("A backup restore point has been created.");
        }
        catch (SqlError $e)
        {
            $this->plugin->getLogger()->critical("An error occurred while connecting to the restore backup database.");
            if (is_null($player) && $p = $this->plugin->getServer()->getPlayerExact($player)) $p->sendMessage("§cAn error occurred while connecting to the restore backup database.");
        }

    }

    public function loadBackup(string $player = null)
    {
        if (!$this->plugin->backupsEnabled) throw new \InvalidArgumentException("Backups must be enabled to load a backup!");

        $this->saveRestore($player);

        $this->plugin->backupDatabase->executeSelect("init.table.getall", [], function(array $rows){

            $this->plugin->database->executeGeneric("init.table.drop");
            $this->plugin->database->executeGeneric("init.table.creation");
            foreach ($rows as $row)
            {
                $this->plugin->database->executeInsert("init.table.createaccount", ["playername" => $row["Player"], "money" => $row["Money"], "transactions" => $row["Transactions"]]);
            }
        });

        // Todo: Try to fix without delay.
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($player) {
                if (is_null($player)) $this->plugin->getLogger()->info("Your server has loaded a backup.");
                else $this->plugin->getLogger()->info($player ." has loaded a backup.");
                foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) $this->plugin->loadData($player);
            }
        ), 10);
    }

    public function restoreBackup(string $player = null)
    {
        if (!$this->plugin->backupsEnabled) throw new \InvalidArgumentException("Backups must be enabled to restore a backup!");

        try
        {
            $this->plugin->restoreDatabase = libasynql::create($this->plugin, ["type" => "sqlite", "sqlite" => ["file" => "restore.sqlite"], 1],[
                "sqlite" => "sqlite.sql",
                "mysql" => "mysql.sql"
            ]);

            $this->plugin->restoreDatabase->executeSelect("init.table.getall", [], function(array $rows){

                $this->plugin->database->executeGeneric("init.table.drop");
                $this->plugin->database->executeGeneric("init.table.creation");
                foreach ($rows as $row)
                {
                    $this->plugin->database->executeInsert("init.table.createaccount", ["playername" => $row["Player"], "money" => $row["Money"], "transactions" => $row["Transactions"]]);
                }
            });
            //$database->close();
            // Todo: Try to fix without delay.
            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function() use ($player) {
                    if (is_null($player)) $this->plugin->getLogger()->info("Your server has restored a backup.");
                    else $this->plugin->getLogger()->info($player ." has restored a backup.");
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) $this->plugin->loadData($player);
                }
            ), 10);
        }
        catch (SqlError $e)
        {
            $this->plugin->getLogger()->critical("An error occurred while connecting to the restore backup database.");
            if (is_null($player) && $p = $this->plugin->getServer()->getPlayerExact($player)) $p->sendMessage("§cAn error occurred while connecting to the restore backup database.");
        }
    }

    public static function getInstance(): self {
        return self::$instance ??= new self(BankUI::getInstance());
    }
}