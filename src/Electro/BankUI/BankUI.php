<?php

namespace Electro\BankUI;

use Electro\BankUI\InterestTask;
use onebone\economyapi\EconomyAPI;
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

class BankUI extends PluginBase implements Listener{

    private static $instance;
    public $playersMoney = [];
    public $playersTransactions = [];
    public $isBeta = false;

    public function onEnable() : void
    {
        $this->saveDefaultConfig();
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if (!file_exists($this->getDataFolder() . "Players")){
            mkdir($this->getDataFolder() . "Players");
        }
        date_default_timezone_set($this->getConfig()->get("timezone"));
        if ($this->getConfig()->get("enable-interest") == true) {
            $this->getScheduler()->scheduleRepeatingTask(new InterestTask($this), 1100);
        }
        if ($this->getConfig()->get("config-ver") != 1) {
            $this->getLogger()->info("§l§cWARNING: §r§cBankUI's config is NOT up to date. Please delete the config.yml and restart the server or the plugin may not work properly.");
        }
        if ($this->isBeta) {
            $this->getLogger()->warning("You are using a Beta/Untested version of BankUI. There may be some bugs. Use this plugin with caution!");
        }
    }

    public function dailyInterest(){
        if (date("H:i") === "12:00"){
            foreach (glob($this->getDataFolder() . "Players/*.yml") as $players) {
                $playerBankMoney = new Config($players);
                $interest = ($this->getConfig()->get("interest-rates") / 100 * $playerBankMoney->get("Money"));
                $playerBankMoney->set("Money", round($playerBankMoney->get("Money") + $interest));
                $playerBankMoney->save();
                if ($playerBankMoney->get('Transactions') === 0){
                    $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aInterest $" . round($interest) . "\n");
                }
                else {
                    $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §a$" . round($interest) . " from interest" . "\n");
                }
                $playerBankMoney->save();
            }
            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayers){
                $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $onlinePlayers->getName() . ".yml", Config::YAML);
                $onlinePlayers->sendMessage("§aYou have earned $" . round(($this->getConfig()->get("interest-rates") / 100) * $playerBankMoney->get("Money")) . " from bank interest");
            }
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if (!file_exists($this->getDataFolder() . "Players/" . $player->getName() . ".yml")) {
            new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML, array(
                "Money" => 0,
                "Transactions" => 0,
            ));
        }
        $this->loadData($player);
    }

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        $this->saveData($player);
        unset($this->playersMoney[$player->getName()]);
    }

    public function onDisable() : void
    {
        $this->saveAllData();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        switch($command->getName()){
            case "bank":
                if($sender instanceof Player){
                    if (isset($args[0]) && $sender->hasPermission("bankui.admi") || isset($args[0]) && $this->$sender->isOp()){
                        if (!file_exists($this->getDataFolder() . "Players/" . $args[0] . ".yml")){
                            $sender->sendMessage("§c§lError: §r§aThis player does not have a bank account");
                            return true;
                        }
                        $this->adminForm($sender, $args[0]);
                        return true;
                    }
                    $this->bankForm($sender);
                }
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
        $form->setContent("Balance: $" . $this->getMoney($player->getName()));
        $form->addButton("§lWithdraw Money\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lDeposit Money\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lTransfer Money\n§r§dClick to transfer...",0,"textures/ui/FriendsIcon");
        $form->addButton("§lTransactions\n§r§dClick to open...",0,"textures/ui/lock_color");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
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
        $form->setContent("Balance: $" . $this->getMoney($target));
        $form->addButton("§lAdd Money\n§r§dClick to add...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lTake Money\n§r§dClick to take...",0,"textures/items/map_filled");
        $form->addButton("§lSet Money\n§r§dClick to set...",0,"textures/ui/FriendsIcon");
        $form->addButton("§lTransactions\n§r§dClick to open...",0,"textures/ui/lock_color");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function adminGiveForm($player, $action, $target)
    {
        $form = new CustomForm(function (Player $player, $data) use ($action, $target){
            $result = $data;
            if ($result === null) {
                return true;
            }

            if (!is_numeric($data[1])){
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
                $this->addTransaction($target, "§aAdmin added $" . $data[1]);
            }
            if ($action == 1){
                $this->takeMoney($target, $data[1]);
                $player->sendMessage("§aYou have took $" . $data[1] . " into " . $target . "'s bank");
                $this->addTransaction($target, "§aAdmin took $" . $data[1]);
            }
            if ($action == 2){
                $this->setMoney($target, $data[1]);
                $player->sendMessage("§aYou have set " . $target . "'s bank balance to $" . $data[1]);
                $this->addTransaction($target, "§aAdmin set balance to $" . $data[1]);
            }
        });

        $form->setTitle("§l" . $target . "'s Bank");;
        $form->addLabel("Balance: $" . $this->getMoney($target));
        if ($action == 0){
            $form->addInput("§rEnter amount to give", "100000");
        }
        if ($action == 1){
            $form->addInput("§rEnter amount to take", "100000");
        }
        if ($action == 2){
            $form->addInput("§rEnter amount to set", "100000");
        }
        $form->sendtoPlayer($player);
        return $form;
    }


    public function withdrawForm($player)
    {
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0:
                    if ($this->getMoney($player->getName()) == 0){
                        $player->sendMessage("§aYou have no money in the bank to withdraw");
                        return true;
                    }
                    EconomyAPI::getInstance()->addMoney($player->getName(), $this->getMoney($player->getName()));
                    $player->sendMessage("§aYou have withdrew $" . $this->getMoney($player->getName()) . " from the bank");
                    $this->addTransaction($player->getName(), $this->getMoney($player->getName()));
                    $this->takeMoney($player->getName(), "§aWithdrew $" . $this->getMoney($player->getName()));
                    break;
                case 1:
                    if ($this->getMoney($player->getName()) == 0){
                        $player->sendMessage("§aYou have no money in the bank to withdraw");
                        return true;
                    }
                    EconomyAPI::getInstance()->addMoney($player->getName(), $this->getMoney($player->getName()) / 2);
                    $player->sendMessage("§aYou have withdrew $" . $this->getMoney($player->getName()) /2 . " from the bank");
                    $this->addTransaction($player->getName(), "§aWithdrew $" . $this->getMoney($player->getName()) / 2);
                    $this->takeMoney($player->getName(), $this->getMoney($player->getName()) / 2);
                     break;
                case 2:
                    $this->withdrawCustomForm($player);
            }
        });

        $form->setTitle("§lWithdraw Menu");
        $form->setContent("Balance: $" . $this->getMoney($player->getName()));
        $form->addButton("§lWithdraw All\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lWithdraw Half\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lWithdraw Custom\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function withdrawCustomForm($player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            $result = $data;
            if ($result === null) {
                return true;
            }

            if ($this->getMoney($player->getName()) == 0){
                $player->sendMessage("§aYou have no money in the bank to withdraw");
                return true;
            }
            if ($this->getMoney($player->getName()) < $data[1]){
                $player->sendMessage("§aYou do not have enough money in your bank to withdraw $" . $data[1]);
                return true;
            }
            if (!is_numeric($data[1])){
                $player->sendMessage("§aYou did not enter a valid amount");
                return true;
            }
            if ($data[1] <= 0){
                $player->sendMessage("§aYou must enter an amount greater than 0");
                return true;
            }
            EconomyAPI::getInstance()->addMoney($player->getName(), $data[1]);
            $player->sendMessage("§aYou have withdrew $" . $data[1] . " from the bank");
            $this->addTransaction($player->getName(), "§aWithdrew $" . $data[1]);
            $this->takeMoney($player->getName(), $data[1]);
        });

        $form->setTitle("§lWithdraw Menu");
        $form->addLabel("Balance: $" . $this->getMoney($player->getName()));
        $form->addInput("§rEnter amount to withdraw", "100000");
        $form->sendtoPlayer($player);
        return $form;
    }


    public function depositForm($player)
    {
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0:
                    $playerMoney = EconomyAPI::getInstance()->myMoney($player);
                    if ($playerMoney == 0){
                        $player->sendMessage("§aYou do not have enough money to deposit into the bank");
                        return true;
                    }
                    $this->addTransaction($player->getName(), "§aDeposited $" . $playerMoney);
                    $this->addMoney($player->getName(), $playerMoney);
                    $player->sendMessage("§aYou have deposited $" . $playerMoney . " into the bank");
                    EconomyAPI::getInstance()->reduceMoney($player, $playerMoney);
                    break;
                case 1:
                    $playerMoney = EconomyAPI::getInstance()->myMoney($player);
                    if ($playerMoney == 0){
                        $player->sendMessage("§aYou do not have enough money to deposit into the bank");
                        return true;
                    }
                    $this->addTransaction($player->getName(), "§aDeposited $" . $playerMoney / 2);
                    $this->addMoney($player->getName(), $playerMoney / 2);
                    $player->sendMessage("§aYou have deposited $" . $playerMoney / 2 . " into the bank");
                    EconomyAPI::getInstance()->reduceMoney($player, $playerMoney / 2);
                    break;
                case 2:
                    $this->depositCustomForm($player);
            }
        });

        $form->setTitle("§lDeposit Menu");
        $form->setContent("Balance: $" . $this->getMoney($player->getName()));
        $form->addButton("§lDeposit All\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lDeposit Half\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lDeposit Custom\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function depositCustomForm($player)
    {
        $form = new CustomForm(function (Player $player, $data) {
            $result = $data;
            if ($result === null) {
                return true;
            }
            $playerMoney = EconomyAPI::getInstance()->myMoney($player);
            if ($playerMoney < $data[1]){
                $player->sendMessage("§aYou do not have enough money to deposit $" . $data[1] . " into the bank");
                return true;
            }
            if (!is_numeric($data[1])){
                $player->sendMessage("§aYou did not enter a valid amount");
                return true;
            }
            if ($data[1] <= 0){
                $player->sendMessage("§aYou must enter an amount greater than 0");
                return true;
            }
            $player->sendMessage("§aYou have deposited $" . $data[1] . " into the bank");
            $this->addTransaction($player->getName(), "§aDeposited $" . $data[1]);
            $this->addMoney($player->getName(), $data[1]);
            EconomyAPI::getInstance()->reduceMoney($player, $data[1]);
        });

        $form->setTitle("§lDeposit Menu");
        $form->addLabel("Balance: $" . $this->getMoney($player->getName()));
        $form->addInput("§rEnter amount to deposit", "100000");
        $form->sendtoPlayer($player);
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
                $player->sendMessage("§aYou must select a valid player");
                return true;
            }

            $index = $data[1];
            $playerName = $list[$index];

            if ($this->getMoney($player->getName()) == 0){
                $player->sendMessage("§aYou have no money in the bank to transfer money");
                return true;
            }
            if ($this->getMoney($player->getName()) < $data[2]){
                $player->sendMessage("§aYou do not have enough money in your bank to transfer $" . $data[2]);
                return true;
            }
            if (!is_numeric($data[2])){
                $player->sendMessage("§aYou did not enter a valid amount");
                return true;
            }
            if ($data[2] <= 0){
                $player->sendMessage("§aYou must transfer at least $1");
                return true;
            }
            $player->sendMessage("§aYou have transferred $" . $data[2] . " into " . $playerName . "'s bank account");
            if (!$this->$player->getPlayer()) {
                return true;
            }
            $otherPlayer = $this->$player->getPlayer();
            $otherPlayer->sendMessage("§a" . $player->getName() . " has transferred $" . $data[2] . " into your bank account");
            $this->addTransaction($player->getName(), "§aTransferred $" . $data[2] . " into " . $playerName . "'s bank account");
            $this->takeMoney($player->getName(), $data[2]);
            $this->addMoney($otherPlayer->getName(), $data[2]);
            });


        $form->setTitle("§lTransfer Menu");
        $form->addLabel("Balance: $" . $this->getMoney($player->getName()));
        $form->addDropdown("Select a Player", $list);
        $form->addInput("§rEnter amount to transfer", "100000");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function transactionsForm($player)
    {
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
        });

        $form->setTitle("§lTransactions Menu");
        if ($this->playersTransactions[$player->getName()] === 0){
            $form->setContent("You have not made any transactions yet");
        }
        else {
            $form->setContent($this->playersTransactions[$player->getName()]);
        }
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
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
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            if ($this->playersTransactions[$player] === 0){
                $form->setContent($player . " has not made any transactions yet");
            }
            else {
                $form->setContent($this->playersTransactions[$player]);
            }
        }
        else
        {
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
            if ($playerBankMoney->get('Transactions') === 0){
                $form->setContent($player . " has not made any transactions yet");
            }
            else {
                $form->setContent($playerBankMoney->get('Transactions'));
            }
            $playerBankMoney->save();
        }
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($sender);
        return $form;
    }

    public function addMoney(string $player, $amount)
    {
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            $this->playersMoney[$player] = $this->playersMoney[$player] + $amount;
        }
        else
        {
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") + $amount);
            $playerBankMoney->save();
        }
    }

    public function takeMoney(string $player, $amount)
    {
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            $this->playersMoney[$player] = $this->playersMoney[$player] - $amount;
        }
        else
        {
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") - $amount);
            $playerBankMoney->save();
        }
    }

    public function setMoney(string $player, $amount)
    {
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            $this->playersMoney[$player] = $amount;
        }
        else
        {
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
            $playerBankMoney->set("Money", $amount);
            $playerBankMoney->save();
        }
    }

    public function getMoney(string $player)
    {
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            return $this->playersMoney[$player];
        }
        else
        {
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
            $money = $playerBankMoney->get("Money");
            $playerBankMoney->save();
            return $money;
        }
    }

    public function addTransaction(string $player, string $message)
    {
        if ($this->getServer()->getPlayerExact($player) instanceof Player)
        {
            if ($this->playersTransactions[$player] === 0){
                $this->playersTransactions[$player] = date("§b[d/m/y]") . "§e - " . $message . "\n";
            }
            else {
                $this->playersTransactions[$player] = date("§b[d/m/y]") . "§e - " . $message . "\n" . $this->playersTransactions[$player];
            }
        }
        else
        {
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
            if ($playerBankMoney->get('Transactions') === 0){
                $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - " . $message . "\n");
            }
            else {
                $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - " . $message . "\n" . $playerBankMoney->get('Transactions'));
            }
            $playerBankMoney->save();
        }


    }

    public function saveData(Player $player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerBankMoney->set("Money", $this->playersMoney[$player->getName()]);
        $playerBankMoney->set("Transactions", $this->playersTransactions[$player->getName()]);
        $playerBankMoney->save();
    }

    public function saveAllData()
    {
        foreach ($this->playersMoney as $player => $amount) {
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
            $playerBankMoney->set("Money", $amount);
            $playerBankMoney->save();
        }
        foreach ($this->playersTransactions as $player => $amount) {
            $playerBankTransactions = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
            $playerBankTransactions->set("Transactions", $this->playersTransactions[$player]);
            $playerBankTransactions->save();
        }
    }

    public function loadData(Player $player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $this->playersMoney[$player->getName()] = $playerBankMoney->get("Money");
        $this->playersTransactions[$player->getName()] = $playerBankMoney->get("Transactions");
    }

    public static function getInstance(): BankUI {
        return self::$instance;
    }

}
