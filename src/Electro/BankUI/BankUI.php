<?php

namespace Electro\BankUI;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use pocketmine\permission\DefaultPermissions;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use Vecnavium\FormsUI\SimpleForm;
use Vecnavium\FormsUI\CustomForm;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\scheduler\ClosureTask;

class BankUI extends PluginBase implements Listener{

    private static $instance;
    public array $playersMoney = [];
    public array $playersTransactions = [];
    public bool $isBeta = false;

    public bool $scoreHud = false;

    public $database;
    public $backupDatabase;
    public $restoreDatabase;

    // 1 = BedrockEconomy, 2 = Capital
    public $economyType = 1;
    public $economyPlugin;

    public int $configVersion = 2;

    public array $messages = [];

    public array $migrationWarning = [];

    public bool $backupsEnabled;
    public array $loadBackupWarning = [];
    public array $restoreBackupWarning = [];

    public function onEnable() : void
    {
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->saveDefaultConfig();
        $this->saveResource("Messages.yml");

        if ($this->getConfig()->get("config-ver") !== $this->configVersion)
        {
            $this->updateConfig();

            $this->getLogger()->critical("BankUI's config has been updated automatically but your changes has been reset so you will need to reconfigure it!");
        }

        $this->database = libasynql::create($this, $this->getConfig()->get("database"), [
            "sqlite" => "sqlite.sql",
            "mysql" => "mysql.sql"
        ]);
        $this->database->executeGeneric("init.table.creation");

        if ($this->getConfig()->get("enable-backups"))
        {
            try
            {
                $this->backupDatabase = libasynql::create($this, $this->getConfig()->get("backup-database"), [
                    "sqlite" => "sqlite.sql",
                    "mysql" => "mysql.sql"
                ]);
                $this->backupsEnabled = true;
                if ($this->getConfig()->get("enable-automatic-backups"))
                {
                    $this->startAutomaticBackups();
                }
            }
            catch (SqlError $e)
            {
                $this->getLogger()->critical("An error occurred while connecting to the backup database. Backups have been disabled, please fix this to enable backups.");
                $this->backupsEnabled = false;
            }
        }

        $this->economyPlugin = $this->getServer()->getPluginManager()->getPlugin("BedrockEconomy");

        if ($this->getServer()->getPluginManager()->getPlugin("ScoreHud")) $this->scoreHud = true;

        // Todo: Add Capital Support
//        if ($this->getConfig()->get('economy-plugin') == 2) $this->economyType = 2;
//        else $this->economyType = 1;
//
//        if ($this->economyType == 2)
//        {
//            if (!$this->getServer()->getPluginManager()->getPlugin("Capital"))
//            {
//                if ($this->getServer()->getPluginManager()->getPlugin("BedrockEconomy"))
//                {
//                    $this->getLogger()->warning("We have detected you are using BedrockEconomy, we have changed your economy plugin to BedrockEconomy but you will need to change this in config.yml to make this option permanent.");
//                    $this->economyType = 1;
//                    return;
//                }
//                $this->getLogger()->critical("You do not have BedrockEconomy or Capital installed, BankUI has been disabled. Please add one of these two plugins then restart your server.");
//                $this->getServer()->getPluginManager()->disablePlugin($this);
//                return;
//            }
//            $this->economyPlugin = $this->getServer()->getPluginManager()->getPlugin("Capital");
//        }
//        else
//        {
//            if (!$this->getServer()->getPluginManager()->getPlugin("BedrockEconomy"))
//            {
//                if ($this->getServer()->getPluginManager()->getPlugin("Capital"))
//                {
//                    $this->getLogger()->warning("We have detected you are using Capital, we have changed your default economy plugin to Capital but you will need to change this in config.yml to make this option permanent.");
//                    $this->economyType = 2;
//                    return;
//                }
//                $this->getLogger()->critical("You do not have BedrockEconomy or Capital installed, BankUI has been disabled. Please add one of these two plugins then restart your server.");
//                $this->getServer()->getPluginManager()->disablePlugin($this);
//                return;
//            }
//            $this->economyPlugin = $this->getServer()->getPluginManager()->getPlugin("BedrockEconomy");
//        }

        if (file_exists($this->getDataFolder() . "Players")) DatabaseMigration::getInstance()->migrateDatabase("YAML", "SQL");

        if ($this->getConfig()->get("enable-interest") == true) {
            $this->interestTask();
        }
        if ($this->isBeta) {
            $this->getLogger()->warning("You are using a Beta/Untested version of BankUI. There may be some bugs. Use this plugin with caution!");
        }

        $this->loadMessages();
    }

    public function startAutomaticBackups()
    {
        $backupTime = $this->getConfig()->get("automatic-backups-time");
        BackupSystem::getInstance()->backupData();
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() {
                BackupSystem::getInstance()->backupData();
            }
        ), $backupTime * 1200);
    }

    public function interestTask()
    {
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() {
                $this->dailyInterest();
            }
        ), 1100);
    }

    public function dailyInterest(){
        if (date("H:i") !== "12:00") return;
        foreach ($this->playersMoney as $players => $money) {
            $interest = ($this->getConfig()->get("interest-rates") / 100 * $this->playersMoney[$players]);
            $this->addMoney($players, $interest);
            $this->addTransaction($players, str_replace("{interest}", round($interest), $this->messages["InterestTransaction"]) . "\n");
            if ($this->getServer()->getPlayerExact($players) && $this->getServer()->getPlayerExact($players) instanceof Player)
            {
                $this->getServer()->getPlayerExact($players)->sendMessage(str_replace("{interest}", round($interest), $this->messages["Interest"]));
            }
        }
    }

    public function loadMessages()
    {
        $msg = new Config($this->getDataFolder() . "messages.yml");
        $this->messages = ["Interest" => $msg->get("Interest"), "Deposit" => $msg->get("Deposit"), "DepositNoMoney" =>$msg->get("DepositNoMoney"), "DepositNotEnoughMoney" => $msg->get("DepositNotEnoughMoney"),
            "Withdraw" => $msg->get("Withdraw"), "WithdrawNoMoney" => $msg->get("WithdrawNoMoney"), "WithdrawNotEnoughMoney" => $msg->get("WithdrawNotEnoughMoney"), "SentTransfer" => $msg->get("SentTransfer"),
            "ReceivedTransfer" => $msg->get("ReceivedTransfer"), "DepositTransaction" => $msg->get("DepositTransaction"), "WithdrawTransaction" => $msg->get("WithdrawTransaction"), "SentTransferTransaction" =>
                $msg->get("SentTransferTransaction"), "ReceivedTransferTransaction" => $msg->get("ReceivedTransferTransaction"), "AdminSetTransaction" => $msg->get("AdminSetTransaction"), "AdminTakeTransaction" =>
                $msg->get("AdminTakeTransaction"), "AdminAddTransaction" => $msg->get("AdminAddTransaction"), "NoNegativeNumbers" => $msg->get("NoNegativeNumbers"), "InvalidAmount" => $msg->get("InvalidAmount"),
            "TransferNoMoney" => $msg->get("TransferNoMoney"), "TransferNotEnoughMoney" => $msg->get("TransferNotEnoughMoney"), "NoTransactions" => $msg->get("NoTransactions"), "InterestTransaction" =>
                $msg->get("InterestTransaction")];
    }


    public function updateConfig()
    {
        copy($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config_old.yml");
        unlink($this->getDataFolder() . "config.yml");
        $this->saveDefaultConfig();
        $this->reloadConfig();
    }

    public function onJoin(PlayerJoinEvent $event){

        $player = $event->getPlayer();
        $this->accountExists($player->getName())->onCompletion(function(bool $exists) use ($player) : void{
            if (!$exists)
            {
                $this->createAccount($player->getName());
                $this->playersMoney[$player->getName()] = 0;
                $this->playersTransactions[$player->getName()] = "";
            }
            else $this->loadData($player);
        }, static fn() => null);
    }

    public function createAccount(string $player, $money = 0, $transactions = "")
    {
        $this->database->executeInsert("init.table.createaccount", ["playername" => $player, "money" => $money, "transactions" => $transactions]);
    }

    public function accountExists(string $player) : \pocketmine\promise\Promise
    {

        $promise = new PromiseResolver();
        $this->database->executeSelect("init.table.playerexists", ["playername" => $player], function(array $rows) use ($promise) {
            if (empty($rows))
            {
                $promise->resolve(false);
            }
            else
            {
                $promise->resolve(true);
            }
        });
        return $promise->getPromise();
    }

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        $this->saveData($player, true);
        unset($this->playersMoney[$player->getName()]);
    }

    public function onDisable() : void
    {
        $this->saveAllData();
        if(isset($this->database)) $this->database->close();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        switch($command->getName()) {
            case "bank":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cYou must be in-game to run this command!");
                    return true;
                }
                if (!isset($args[0]))
                {
                    $this->bankForm($sender);
                    return true;
                }
                if (!$sender->hasPermission("bankui.admin") && !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR))
                {
                    $sender->sendMessage('§cYou do not have permissions to manage other players banks.');
                    return true;
                }
                if ($args[0] == "migrate") {
                    if (!$sender->hasPermission("bankui.admin.migrate") && !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR))
                    {
                        $sender->sendMessage('§cYou must have the "bankui.admin.migrate" permission to migrate your databases.');
                        return true;
                    }
                    $currentDBType = $this->getConfig()->getNested("database.type");

                    if ($currentDBType == "mysql") {
                        $migrateFrom = "MySQL";
                        $migrateTo = "SQLite";
                    } else {
                        $migrateFrom = "SQLite";
                        $migrateTo = "MySQL";
                    }
                    if (in_array($sender->getName(), $this->migrationWarning)) {
                        $sender->sendMessage("§l§aGetting Migration Ready: §r§eWe are getting your databases ready to begin migration!");
                        unset($this->migrationWarning[array_search($sender->getName(), $this->migrationWarning)]);
                        $this->saveAllData();
                        DatabaseMigration::getInstance()->migrateDatabase("sql", "sql", $sender);
                        return true;
                    }
                    $sender->sendMessage("§c§lWARNING: §r§aYou are about to migrate your " . $migrateFrom . " database to " . $migrateTo . ". Doing so you will LOSE ALL data to your " . $migrateTo . " database and all of your data from your " . $migrateFrom . " database will be sent to your " . $migrateTo . " database. If you don't know what this does or you have no idea what you are doing, DO NOT USE THIS OR YOU MAY LOSE YOUR DATA! Note: We will not update your default database so after migrating your data, you will need to change your database type to " . $migrateTo . " in config.yml. Type this command again if you are sure you would like to migrate your database.");
                    $this->migrationWarning[] = $sender->getName();
                    return true;
                }
                if ($args[0] == "backup")
                {
                    if (!$sender->hasPermission("bankui.admin.backup") && !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR))
                    {
                        $sender->sendMessage('§cYou must have the "bankui.admin.backup" permission to save, load, and restore backups.');
                        return true;
                    }
                    if (!isset($args[1]) || $args[1] !== "save" && $args[1] !== "load" && $args[1] !== "restore")
                    {
                        $sender->sendMessage("§cUsage: §a/bank backup <save | load | restore>");
                        return true;
                    }
                    if ($args[1] == "save")
                    {
                        BackupSystem::getInstance()->backupData($sender->getName());
                        $sender->sendMessage('§aYou have backed up the bank!');
                        return true;
                    }
                    else if ($args[1] == "restore")
                    {
                        if (in_array($sender->getName(), $this->restoreBackupWarning)) {
                            unset($this->restoreBackupWarning[array_search($sender->getName(), $this->restoreBackupWarning)]);
                            BackupSystem::getInstance()->restoreBackup($sender->getName());
                            $sender->sendMessage("§aYou have restored the server from the most recent backup! If this was done by mistake, immediately type §c/bank backup load§a before a new backup saves!");
                            return true;
                        }
                        $sender->sendMessage("§c§lWARNING: §r§aYou are about to restore your server from the most recent backup. Doing so you will lose all of your data made since the last backup. If you do this by mistake, immediately type §c/bank backup load§a before a new backup saves! If you are you are sure you would like to restore from the most recent backup, type this command again.");
                        $this->restoreBackupWarning[] = $sender->getName();
                        return true;
                    }
                    if (in_array($sender->getName(), $this->loadBackupWarning)) {
                        unset($this->loadBackupWarning[array_search($sender->getName(), $this->loadBackupWarning)]);
                        BackupSystem::getInstance()->loadBackup($sender->getName());
                        $sender->sendMessage("§aYou have loaded the server's most recent backup! If this was done by mistake, immediately type §c/bank backup restore§a!");
                        return true;
                    }
                    $sender->sendMessage("§c§lWARNING: §r§aYou are about to load your server's most recent backup. Doing so you will lose all of your data made since the last backup. If you do this by mistake, you can use §c/bank backup restore §abut this will only restore that last time you loaded a backup so be careful! If you are you are sure you would like to load the most recent backup, type this command again.");
                    $this->loadBackupWarning[] = $sender->getName();
                    return true;
                }
                $this->accountExists($args[0])->onCompletion(function (bool $exists) use ($sender, $args): void {
                    if ($exists) $this->adminForm($sender, $args[0]);
                    else $sender->sendMessage("§c§lError: §r§aThis player does not have a bank account");
                }, static fn() => null);
        }
        return true;
    }

    public function bankForm($player)
    {
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0:
                    $this->withdrawForm($player);
                    break;
                case 1:
                    $this->depositForm($player);
                    break;
                case 2:
                    $this->transferCustomForm($player);
                    break;
                case 3:
                    $this->transactionsForm($player);
            }
        });

        $form->setTitle("§lBank Menu");
        $this->getMoney($player->getName())->onCompletion(function(float $money) use($form): void{
            $form->setContent("Balance: $" . $money);
        }, static fn() => null);
        $form->addButton("§lWithdraw Money\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lDeposit Money\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lTransfer Money\n§r§dClick to transfer...",0,"textures/ui/FriendsIcon");
        $form->addButton("§lTransactions\n§r§dClick to open...",0,"textures/ui/lock_color");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $player->sendForm($form);
        return $form;
    }

    public function adminForm($player, $target)
    {
        $form = new SimpleForm(function (Player $player, int $data = null) use ($target){
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0:
                    $this->adminGiveForm($player, 0, $target);
                    break;
                case 1:
                    $this->adminGiveForm($player, 1, $target);
                    break;
                case 2:
                    $this->adminGiveForm($player, 2, $target);
                    break;
                case 3:
                    $this->otherTransactionsForm($player, $target);
            }
        });

        $form->setTitle("§l" . $target . "'s Bank");
        $this->getMoney($target)->onCompletion(function(float $money) use($form): void{
            $form->setContent("Balance: $" . $money);
        }, static fn() => null);
        $form->addButton("§lAdd Money\n§r§dClick to add...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lTake Money\n§r§dClick to take...",0,"textures/items/map_filled");
        $form->addButton("§lSet Money\n§r§dClick to set...",0,"textures/ui/FriendsIcon");
        $form->addButton("§lTransactions\n§r§dClick to open...",0,"textures/ui/lock_color");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $player->sendForm($form);
        return $form;
    }

    public function adminGiveForm($player, $action, $target)
    {
        $form = new CustomForm(function (Player $player, $data) use ($action, $target){
            $result = $data;
            if ($result === null) {
                return true;
            }

            if (!isset($data[1]) || !is_numeric($data[1])){
                $player->sendMessage("§aYou did not enter a valid amount");
                return true;
            }
            if ($data[1] <= 0){
                $player->sendMessage("§aYou must enter an amount greater than 0");
                return true;
            }

            if ($action == 0){
                $this->addMoney($target, $data[1]);
                $player->sendMessage("§aYou have added $" . $data[1] . " into " . $target . "'s bank");
                $this->addTransaction($target, str_replace("{amount}", $data[1], $this->messages["AdminAddTransaction"]));
            }
            if ($action == 1){
                $this->takeMoney($target, $data[1]);
                $player->sendMessage("§aYou have took $" . $data[1] . " into " . $target . "'s bank");
                $this->addTransaction($target, str_replace("{amount}", $data[1], $this->messages["AdminTakeTransaction"]));
            }
            if ($action == 2){
                $this->setMoney($target, $data[1]);
                $player->sendMessage("§aYou have set " . $target . "'s bank balance to $" . $data[1]);
                $this->addTransaction($target, str_replace("{amount}", $data[1], $this->messages["AdminSetTransaction"]));
            }
        });

        $form->setTitle("§l" . $target . "'s Bank");;
        $this->getMoney($target)->onCompletion(function(float $money) use($form): void{
            $form->addLabel("Balance: $" . $money);
        }, static fn() => null);
        if ($action == 0){
            $form->addInput("§rEnter amount to give", "100000");
        }
        if ($action == 1){
            $form->addInput("§rEnter amount to take", "100000");
        }
        if ($action == 2){
            $form->addInput("§rEnter amount to set", "100000");
        }
        $player->sendForm($form);
        return $form;
    }


    public function withdrawForm($player)
    {
        $form = new SimpleForm(function (Player $player, int $data = null){
            if ($data === null) {
                return true;
            }
            switch ($data) {
                case 0:
                    $this->getMoney($player->getName())->onCompletion(function(float $money) use ($player): void{
                        $money = ceil($money);
                        if ($money == 0){
                            $player->sendMessage($this->messages["WithdrawNoMoney"]);
                            return;
                        }
                        $this->addEconomyMoney($player->getName(),$money)->onCompletion(function (bool $updated) use ($money, $player): void{
                            if (!$updated) {
                                $player->sendMessage("§cAn error occurred");
                                return;
                            }
                            $player->sendMessage(str_replace("{amount}", $money, $this->messages["Withdraw"]));
                            $this->addTransaction($player->getName(), str_replace("{amount}", $money, $this->messages["WithdrawTransaction"]));
                            $this->takeMoney($player->getName(), $money);
                        }, static fn() => null);
                    }, static fn() => null);
                    break;
                case 1:
                    $this->getMoney($player->getName())->onCompletion(function(float $money) use ($player): void{
                        $money = ceil($money / 2);
                        if ($money == 0){
                            $player->sendMessage($this->messages["WithdrawNoMoney"]);
                            return;
                        }
                        $this->addEconomyMoney($player->getName(),$money)->onCompletion(function (bool $updated) use ($money, $player): void{
                            if (!$updated) {
                                $player->sendMessage("§cAn error occurred");
                                return;
                            }
                            $player->sendMessage(str_replace("{amount}", $money, $this->messages["Withdraw"]));
                            $this->addTransaction($player->getName(), str_replace("{amount}", $money, $this->messages["WithdrawTransaction"]));
                            $this->takeMoney($player->getName(), $money);
                        }, static fn() => null);
                    }, static fn() => null);
                    break;
                case 2:
                    $this->withdrawCustomForm($player);
            }
        });

        $form->setTitle("§lWithdraw Menu");
        $this->getMoney($player->getName())->onCompletion(function(float $money) use($form): void{
            $form->setContent("Balance: $" . $money);
        }, static fn() => null);
        $form->addButton("§lWithdraw All\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lWithdraw Half\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lWithdraw Custom\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $player->sendForm($form);
        return $form;
    }

    public function withdrawCustomForm($player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }
            $data[1] = ceil($data[1]);

            $this->getMoney($player->getName())->onCompletion(function(float $money) use ($player, $data): void{
                if ($money == 0){
                    $player->sendMessage(str_replace("{amount}", $data[1], $this->messages["WithdrawNoMoney"]));
                    return;
                }
                if ($money < $data[1]){
                    $player->sendMessage(str_replace("{amount}", $data[1], $this->messages["WithdrawNotEnoughMoney"]));
                    return;
                }
                if (!is_numeric($data[1])){
                    $this->addTransaction($player->getName(), $this->messages["InvalidAmount"]);
                    return;
                }
                if ($data[1] <= 0){
                    $player->sendMessage($this->messages["NoNegativeNumbers"]);
                    return;
                }
                $this->addEconomyMoney($player->getName(),$money)->onCompletion(function (bool $updated) use ($data, $player): void{
                    if (!$updated) {
                        $player->sendMessage("§cAn error occurred");
                        return;
                    }
                    $player->sendMessage(str_replace("{amount}", $data[1], $this->messages["Withdraw"]));
                    $this->addTransaction($player->getName(), str_replace("{amount}", $data[1], $this->messages["WithdrawTransaction"]));
                }, static fn() => null);
            }, static fn() => null);
        });

        $form->setTitle("§lWithdraw Menu");
        $this->getMoney($player->getName())->onCompletion(function(float $money) use($form): void{
            $form->addLabel("Balance: $" . $money);
        }, static fn() => null);
        $form->addInput("§rEnter amount to withdraw", "100000");
        $player->sendForm($form);
        return $form;
    }


    public function depositForm($player)
    {
        $form = new SimpleForm(function (Player $player, int $data = null){
            if ($data === null) {
                return true;
            }
            switch ($data) {
                case 0:
                    $this->getEconomyMoney($player->getName())->onCompletion(function (float $balance) use ($player): void{
                        $balance = ceil($balance);
                        if ($balance <= 0){
                            $player->sendMessage(str_replace("{amount}", $balance, $this->messages["DepositNotEnoughMoney"]));
                            return;
                        }
                        $this->takeEconomyMoney($player->getName(),$balance)->onCompletion(function (bool $updated) use ($balance, $player): void{
                            if (!$updated) {
                                if (!is_numeric($balance)) {
                                    $player->sendMessage($this->messages["InvalidAmount"]);
                                } else {
                                    $player->sendMessage(str_replace("{amount}", $balance, $this->messages["DepositNotEnoughMoney"]));
                                }
                                return;
                            }
                            $this->addMoney($player->getName(), $balance);
                            $player->sendMessage(str_replace("{amount}", $balance, $this->messages["Deposit"]));
                            $this->addTransaction($player->getName(), str_replace("{amount}", $balance, $this->messages["DepositTransaction"]));
                        }, function () use ($player): void{
                            $player->sendMessage("§cYou do not have enough money!");
                        });
                    }, static fn() => null);
                    break;
                case 1:
                    $this->getEconomyMoney($player->getName())->onCompletion(function (float $balance) use ($player): void{
                        $balance = ceil($balance / 2);
                        if ($balance <= 1){
                            $player->sendMessage("§cYou must have more than $1!");
                            return;
                        }
                        $this->takeEconomyMoney($player->getName(),$balance)->onCompletion(function (bool $updated) use ($balance, $player): void{
                            if (!$updated) {
                                if (!is_numeric($balance)) {
                                    $player->sendMessage($this->messages["InvalidAmount"]);
                                } else {
                                    $player->sendMessage(str_replace("{amount}", $balance, $this->messages["DepositNotEnoughMoney"]));
                                }
                                return;
                            }
                            $this->addMoney($player->getName(), $balance);
                            $player->sendMessage(str_replace("{amount}", $balance, $this->messages["Deposit"]));
                            $this->addTransaction($player->getName(), str_replace("{amount}", $balance, $this->messages["DepositTransaction"]));
                        }, function () use ($player): void{
                            $player->sendMessage("§cYou do not have enough money!");
                        });
                    }, static fn() => null);
                    break;
                case 2:
                    $this->depositCustomForm($player);
            }
        });

        $form->setTitle("§lDeposit Menu");
        $this->getMoney($player->getName())->onCompletion(function(float $money) use($form): void{
            $form->setContent("Balance: $" . $money);
        }, static fn() => null);
        $form->addButton("§lDeposit All\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lDeposit Half\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lDeposit Custom\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $player->sendForm($form);
        return $form;
    }

    public function depositCustomForm($player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            if ($data === null) {
                return true;
            }

            $data[1] = ceil($data[1]);
            $this->takeEconomyMoney($player->getName(),$data[1])->onCompletion(function (bool $updated) use ($data, $player): void{
                if (!$updated) {
                    if (!is_numeric($data[1])) {
                        $player->sendMessage($this->messages["InvalidAmount"]);
                    } else if ($data[1] <= 0) {
                        $player->sendMessage("§cYou must enter an amount greater than 0");
                        $player->sendMessage($this->messages["NoNegativeNumbers"]);
                    } else {
                        $player->sendMessage(str_replace("{amount}", $data[1], $this->messages["DepositNotEnoughMoney"]));
                    }
                    return;
                }
                $this->addMoney($player->getName(), $data[1]);
                $player->sendMessage(str_replace("{amount}", $data[1], $this->messages["Deposit"]));
                $this->addTransaction($player->getName(), str_replace("{amount}", $data[1], $this->messages["DepositTransaction"]));
            }, static fn() => null);
        });

        $form->setTitle("§lDeposit Menu");
        $this->getMoney($player->getName())->onCompletion(function(float $money) use($form): void{
            $form->addLabel("Balance: $" . $money);
        }, static fn() => null);
        $form->addInput("§rEnter amount to deposit", "100000");
        $player->sendForm($form);
        return $form;
    }

    public function transferCustomForm($player)
    {

        $list = [];
        foreach ($this->getServer()->getOnlinePlayers() as $players){
            if ($players->getName() !== $player->getName()) {
                $list[] = $players->getName();
            }
        }

        $form = new CustomForm(function (Player $player, $data) use ($list) {
            $result = $data;
            if ($result === null) {
                return true;
            }

            if (!isset($list[$data[1]])){
                $player->sendMessage("§cYou must select a valid player!");
                return true;
            }

            $index = $data[1];
            $playerName = $list[$index];

            $this->getMoney($player->getName())->onCompletion(function(float $money) use ($player, $playerName, $data): void{
                if ($money == 0){
                    $player->sendMessage($this->messages["TransferNoMoney"]);
                    return;
                }
                if ($money < $data[2]){
                    $player->sendMessage(str_replace("{amount}", $data[2], $this->messages["TransferNotEnoughMoney"]));
                    return;
                }
                if (!is_numeric($data[2])){
                    $player->sendMessage($this->messages["InvalidAmount"]);
                    return;
                }
                if ($data[2] <= 0){
                    $player->sendMessage($this->messages["NoNegativeNumbers"]);
                    return;
                }
                if (!$this->getServer()->getPlayerExact($playerName)) {
                    $player->sendMessage("§cThe player you have selected in an invalid player!");
                    return;
                }
                $player->sendMessage(str_replace(["{amount}", "{player}"], [$data[2], $playerName], $this->messages["SentTransfer"]));
                $otherPlayer = $this->getServer()->getPlayerExact($playerName);
                $otherPlayer->sendMessage(str_replace(["{amount}", "{player}"], [$data[2], $player->getName()], $this->messages["ReceivedTransfer"]));
                $this->addTransaction($player->getName(), str_replace(["{amount}", "{player}"], [$data[2], $playerName], $this->messages["SentTransferTransaction"]));
                $this->addTransaction($playerName, str_replace(["{amount}", "{player}"], [$data[2], $player->getName()], $this->messages["ReceivedTransferTransaction"]));
                $this->takeMoney($player->getName(), $data[2]);
                $this->addMoney($otherPlayer->getName(), $data[2]);
            }, static fn() => null);
        });


        $form->setTitle("§lTransfer Menu");
        $this->getMoney($player->getName())->onCompletion(function(float $money) use($form): void{
            $form->addLabel("Balance: $" . $money);
        }, static fn() => null);
        $form->addDropdown("Select a Player", $list);
        $form->addInput("§rEnter amount to transfer", "100000");
        $player->sendForm($form);
        return $form;
    }

    public function transactionsForm($player)
    {
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
        });

        $form->setTitle("§lTransactions Menu");
        if (empty($this->playersTransactions[$player->getName()])){
            $form->setContent($this->messages["NoTransactions"]);
        }
        else {
            $form->setContent($this->playersTransactions[$player->getName()]);
        }
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $player->sendForm($form);
        return $form;
    }

    public function otherTransactionsForm($sender, $player)
    {
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
        });

        $form->setTitle("§l" . $player . "'s Transactions");
        $this->getTransactions($player)->onCompletion(function(string $transactions) use($form, $player): void{
            if (empty($transactions)){
                $form->setContent("§c" . $player . " has not made any transactions yet!");
            }
            else {
                $form->setContent($transactions);
            }
        }, static fn() => null);
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $sender->sendForm($form);
        return $form;
    }

    public function addEconomyMoney(string $player, $amount)
    {
        $promise = new PromiseResolver();
        BedrockEconomyAPI::beta()->add($player, $amount)->onCompletion(function () use ($promise): void {
            $promise->resolve(true);
        }, function () use ($promise): void {
            $promise->resolve(false);
        });
        return $promise->getPromise();
    }

    public function takeEconomyMoney(string $player, $amount) : Promise
    {
        $promise = new PromiseResolver();
        BedrockEconomyAPI::beta()->deduct($player, $amount)->onCompletion(function () use ($promise): void {
            $promise->resolve(true);
        }, function () use ($promise): void {
            $promise->resolve(false);
        });
        return $promise->getPromise();
    }

    public function getEconomyMoney(string $player) : Promise
    {
        $promise = new PromiseResolver();
        BedrockEconomyAPI::beta()->get($player)->onCompletion(function (float $balance) use ($promise): void {
            $promise->resolve($balance);
        }, function (float $balance) use ($promise): void {
            $promise->resolve($balance);
        });
        return $promise->getPromise();
    }

    public function addMoney(string $player, $amount)
    {
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            $this->playersMoney[$player] = $this->playersMoney[$player] + (float)$amount;
            if ($this->scoreHud) ScoreHudTags::updateScoreHud($this->getServer()->getPlayerExact($player), $this->playersMoney[$player]);
        }
        else
        {
            $this->database->executeInsert("init.table.addmoney", ["playername" => $player, "money" => (float)$amount]);
        }
    }

    public function takeMoney(string $player, $amount)
    {
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            $this->playersMoney[$player] = $this->playersMoney[$player] - (float)$amount;
            if ($this->scoreHud) ScoreHudTags::updateScoreHud($this->getServer()->getPlayerExact($player), $this->playersMoney[$player]);
        }
        else
        {
            $this->database->executeInsert("init.table.takemoney", ["playername" => $player, "money" => (float)$amount]);
        }
    }

    public function setMoney(string $player, $amount, bool $forceToDatabase = false)
    {
        if ($forceToDatabase || !$this->getServer()->getPlayerExact($player) instanceof Player)
        {
            $this->database->executeInsert("init.table.setmoney", ["playername" => $player, "money" => (float)$amount]);
        }
        else
        {
            $this->playersMoney[$player] = (float)$amount;
            if ($this->scoreHud) ScoreHudTags::updateScoreHud($this->getServer()->getPlayerExact($player), (float)$amount);
        }
    }

    public function getMoney(string $player, bool $forceFromDatabase = false)
    {
        if ($forceFromDatabase || !$this->getServer()->getPlayerExact($player) instanceof Player)
        {
            $promise = new PromiseResolver();
            $this->database->executeSelect("init.table.getmoney", ["playername" => $player], function(array $rows) use ($promise) {
                if (empty($rows[0])) $promise->resolve(-1);
                else $promise->resolve($rows[0]["Money"]);
            });
            return $promise->getPromise();
        }
        else
        {
            $promise = new PromiseResolver();
            $promise->resolve((float)$this->playersMoney[$player]);
            return $promise->getPromise();
        }
    }

    public function addTransaction(string $player, string $transaction)
    {
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            if (empty($this->playersTransactions[$player])){
                $this->playersTransactions[$player] = date("§b[d/m/y]") . "§e - " . $transaction . "\n";
            }
            else {
                $this->playersTransactions[$player] = date("§b[d/m/y]") . "§e - " . $transaction . "\n" . $this->playersTransactions[$player];
            }
        }
        else
        {
            $this->database->executeInsert("init.table.addtransaction", ["playername" => $player, "transaction" => date("§b[d/m/y]") . "§e - " . $transaction . "\n"]);
        }
    }

    public function setTransactions(string $player, string $transactions, bool $forceToDatabase = false)
    {
        if ($forceToDatabase || !$this->getServer()->getPlayerExact($player) instanceof Player)
        {
            $this->database->executeInsert("init.table.settransactions", ["playername" => $player, "transactions" => $transactions]);
        }
        else
        {
            $this->playersTransactions[$player] = date("§b[d/m/y]") . "§e - " . $transactions;
        }
    }

    public function getTransactions(string $player, $forceFromDatabase = false)
    {
        if ($forceFromDatabase || !$this->getServer()->getPlayerExact($player) instanceof Player) {
            $promise = new PromiseResolver();
            $this->database->executeSelect("init.table.gettransactions", ["playername" => $player], function (array $rows) use ($promise) {
                if (empty($rows[0])) $promise->resolve("");
                else $promise->resolve($rows[0]["Transactions"]);
            });
            return $promise->getPromise();
        } else {
            $promise = new PromiseResolver();
            $promise->resolve($this->playersTransactions[$player]);
            return $promise->getPromise();
        }
    }

    public function saveData(Player $player, $removeFromArray = false)
    {
        if (!array_key_exists($player->getName(), $this->playersMoney) || !isset($this->playersMoney[$player->getName()])) return;
        if (!isset($this->playersTransactions[$player->getName()]) || is_null($this->playersTransactions[$player->getName()])) $transactions = "";
        else $transactions = $this->playersTransactions[$player->getName()];
        $this->setMoney($player->getName(), $this->playersMoney[$player->getName()], true);
        $this->setTransactions($player->getName(), $transactions, true);
        if ($removeFromArray)
        {
            unset($this->playersMoney[$player->getName()]);
            unset($this->playersTransactions[$player->getName()]);
        }
    }

    public function saveAllData($removeFromArray = false)
    {
        foreach ($this->playersMoney as $player => $amount) {
            $this->setMoney($player, $amount, true);
            if ($removeFromArray) unset($this->playersMoney[$player]);
        }
        foreach ($this->playersTransactions as $player => $transactions) {
            if (!isset($transactions)) $transactions = "";
            $this->setTransactions($player, $transactions, true);
            if ($removeFromArray) unset($this->playersTransactions[$player]);
        }
    }

    public function loadData(Player $player)
    {
        $this->getMoney($player->getName(), true)->onCompletion(function(float $money) use($player): void{
            $this->playersMoney[$player->getName()] = $money;
            if ($this->scoreHud) ScoreHudTags::updateScoreHud($player, (float)$money);
//            while (true)
//            {
//                echo ($money);
//                $loaded = false;
//                $this->getMoney($player->getName(), true)->onCompletion(function(float $money) use($player, &$loaded): void{
//
//                    if (!empty($money) && $money !== -1)
//                    {
//                        $loaded = true;
//                        $this->playersMoney[$player->getName()] = $money;
//                        return;
//                    }
//                }, static fn() => null);
//                if ($loaded) break;
//            }
        }, static fn() => null);

        $this->getTransactions($player->getName(), true)->onCompletion(function(string $transactions) use($player): void{
            $this->playersTransactions[$player->getName()] = $transactions;
        }, static fn() => null);
    }

    public static function getInstance(): BankUI {
        return self::$instance;
    }
}
