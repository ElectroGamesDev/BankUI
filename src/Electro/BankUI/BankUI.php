<?php

namespace Electro\BankUI;

use onebone\economyapi\EconomyAPI;
use jojoe77777\FormAPI\SimpleForm;

use pocketmine\Server;
use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\utils\Config;

class BankUI extends PluginBase implements Listener{

    private static $instance;
    public $player;

    public function onEnable()
    {
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if (!file_exists($this->getDataFolder() . "Players")){
            mkdir($this->getDataFolder() . "Players");
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();

        if (!file_exists($this->getDataFolder() . "Players/" . $player->getName() . ".yml")) {
            new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML, array(
                "Money" => 0,
            ));
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        switch($command->getName()){
            case "bank":
                if($sender instanceof Player){
                    $this->bankForm($sender);
                }
        }
        return true;
    }

    public function bankForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0;
                    $this->withdrawForm($player);
            }
            switch ($result) {
                case 1;
                    $this->depositForm($player);
            }
        });

        $form->setTitle("§lBank");
        $form->setContent("Balance: $" . $playerBankMoney->get("Money"));
        $form->addButton("§lWithdraw Money\n§r§dClick to withdraw...");
        $form->addButton("§lDeposit Money\n§r§dClick to deposit...");
        $form->addButton("§l§cEXIT\n§r§dClick to Close...");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function withdrawForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0;
                    $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
                    if ($playerBankMoney->get("Money") == 0){
                        $player->sendMessage("§aYou have no money in the bank to withdraw");
                        return true;
                    }
                    EconomyAPI::getInstance()->addMoney($player, $playerBankMoney->get("Money"));
                    $player->sendMessage("§aYou have withdrew $" . $playerBankMoney->get("Money") . " from the bank");
                    $playerBankMoney->set("Money", 0);
                    $playerBankMoney->save();
            }
            switch ($result) {
                case 1;
                    $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
                    if ($playerBankMoney->get("Money") == 0){
                        $player->sendMessage("§aYou have no money in the bank to withdraw");
                        return true;
                    }
                    EconomyAPI::getInstance()->addMoney($player, $playerBankMoney->get("Money") / 2);
                    $player->sendMessage("§aYou have withdrew $" . $playerBankMoney->get("Money") . " from the bank");
                    $playerBankMoney->set("Money", $playerBankMoney->get("Money") / 2);
                    $playerBankMoney->save();
            }
            switch ($result) {
                case 2;
                    $this->withdrawCustomForm($player);
            }
        });

        $form->setTitle("§lWithdraw");
        $form->setContent("Balance: $" . $playerBankMoney->get("Money"));
        $form->addButton("§lWithdraw All\n§r§dClick to withdraw...");
        $form->addButton("§lWithdraw Half\n§r§dClick to withdraw...");
        $form->addButton("§lWithdraw Custom\n§r§dClick to withdraw...");
        $form->addButton("§l§cEXIT\n§r§dClick to Close...");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function withdrawCustomForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $player, array $data = null) {
            $result = $data;
            if ($result === null) {
                return true;
            }

            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
            if ($playerBankMoney->get("Money") == 0){
                $player->sendMessage("§aYou have no money in the bank to withdraw");
                return true;
            }
            if ($playerBankMoney->get("Money") < $data[1]){
                $player->sendMessage("§aYou do not have enough money in your bank to withdraw $" . $data[1]);
                return true;
            }
            EconomyAPI::getInstance()->addMoney($player, $data[1]);
            $player->sendMessage("§aYou have withdrew $" . $data[1] . " from the bank");
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") - $data[1]);
            $playerBankMoney->save();
        });

        $form->setTitle("§lWithdraw");
        $form->addLabel("Balance: $" . $playerBankMoney->get("Money"));
        $form->addInput("§r§lEnter amount to withdraw", "100000");
        $form->sendtoPlayer($player);
        return $form;
    }


    public function depositForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0;
                    $playerMoney = EconomyAPI::getInstance()->myMoney($player);
                    $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
                    if ($playerMoney == 0){
                        $player->sendMessage("§aYou do not have enough money to deposit into the bank");
                        return true;
                    }
                    $playerBankMoney->set("Money", $playerBankMoney->get("Money") + $playerMoney);
                    $player->sendMessage("§aYou have deposited $" . $playerMoney . " into the bank");
                    EconomyAPI::getInstance()->reduceMoney($player, $playerMoney);
                    $playerBankMoney->save();
            }
            switch ($result) {
                case 1;
                    $playerMoney = EconomyAPI::getInstance()->myMoney($player);
                    $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
                    if ($playerMoney == 0){
                        $player->sendMessage("§aYou do not have enough money to deposit into the bank");
                        return true;
                    }
                    $playerBankMoney->set("Money", $playerBankMoney->get("Money") + ($playerMoney / 2));
                    $player->sendMessage("§aYou have deposited $" . $playerMoney / 2 . " into the bank");
                    EconomyAPI::getInstance()->reduceMoney($player, $playerMoney / 2);
                    $playerBankMoney->save();
            }
            switch ($result) {
                case 2;
                    $this->depositCustomForm($player);
            }
        });

        $form->setTitle("§lDeposit");
        $form->setContent("Balance: $" . $playerBankMoney->get("Money"));
        $form->addButton("§lDeposit All\n§r§dClick to deposit...");
        $form->addButton("§lDeposit Half\n§r§dClick to deposit...");
        $form->addButton("§lDeposit Custom\n§r§dClick to deposit...");
        $form->addButton("§l§cEXIT\n§r§dClick to Close...");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function depositCustomForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $player, array $data = null) {
            $result = $data;
            if ($result === null) {
                return true;
            }
            $playerMoney = EconomyAPI::getInstance()->myMoney($player);
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
            if ($playerMoney == 0){
                $player->sendMessage("§aYou do not have enough money to deposit into the bank");
                return true;
            }
            if ($playerMoney < $data[1]){
                $player->sendMessage("§aYou do not have enough money to deposit $" . $data[1] . " into the bank");
                return true;
            }
            $player->sendMessage("§aYou have deposited $" . $data[1] . " into the bank");
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") + $data[1]);
            EconomyAPI::getInstance()->reduceMoney($player, $data[1]);
            $playerBankMoney->save();
        });

        $form->setTitle("§lDeposit");
        $form->addLabel("Balance: $" . $playerBankMoney->get("Money"));
        $form->addInput("§r§lEnter amount to deposit", "100000");
        $form->sendtoPlayer($player);
        return $form;
    }

    public static function getInstance(): BankUI {
        return self::$instance;
    }

}
