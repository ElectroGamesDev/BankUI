<?php

/*
 * [FORKED] A BankUI Plugin For PocketMine-MP
 * Copyright (C) 2021 ElectroGamesYT, KygekDev
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace Electro\BankUI;

use onebone\economyapi\EconomyAPI;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;

use pocketmine\block\Block;
use pocketmine\Server;
use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\utils\Config;

class BankUI extends PluginBase implements Listener{

    private const IS_BETA = true;

    private static $instance;
    public $player;
    public $playerList = [];

    public function onEnable()
    {
        self::$instance = $this;
        $this->getLogger()->warning("You are using forked version of BankUI, maintained by KygekDev. There might be no support from the original developer if you use this plugin.");
        /** @phpstan-ignore-next-line */
        if (self::IS_BETA) {
            $this->getLogger()->warning("This plugin is under BETA. There may be some bugs. Use this plugin with caution. DSIDWY!");
        }
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
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§aThis command can only be executed by a player!");
                    return true;
                }
                $this->bankForm($sender);
        }
    }

    public function bankForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
        $form = new SimpleForm(function (Player $player, int $data = null) use ($playerBankMoney){
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
            switch ($result) {
                case 2;
                    if ($playerBankMoney->get("Money") == 0){
                        $player->sendMessage("§aYou have no money in the bank to transfer money");
                        return true;
                    }
                    $this->transferCustomForm($player);
            }
        });

        $form->setTitle("§lBank");
        $form->setContent("Balance: $" . $playerBankMoney->get("Money"));
        $form->addButton("§lWithdraw Money\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lDeposit Money\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lTransfer Money\n§r§dClick to transfer...",0,"textures/ui/FriendsIcon");
        $form->addButton("§l§cEXIT\n§r§dClick to Close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function withdrawForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
        $form = new SimpleForm(function (Player $player, int $data = null) use ($playerBankMoney){
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
                    if ($playerBankMoney->get("Money") == 0){
                        $player->sendMessage("§aYou have no money in the bank to withdraw");
                        return true;
                    }
                    $this->withdrawCustomForm($player);
            }
        });

        $form->setTitle("§lWithdraw");
        $form->setContent("Balance: $" . $playerBankMoney->get("Money"));
        $form->addButton("§lWithdraw All\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lWithdraw Half\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lWithdraw Custom\n§r§dClick to withdraw...",0,"textures/ui/icon_book_writable");
        $form->addButton("§l§cEXIT\n§r§dClick to Close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function withdrawCustomForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createCustomForm(function (Player $player, array $data = null) {
        $form = new CustomForm(function (Player $player, $data) {
            $result = $data;
            if ($result === null) {
                return true;
            }

            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
            EconomyAPI::getInstance()->addMoney($player, $data[1]);
            $player->sendMessage("§aYou have withdrew $" . $data[1] . " from the bank");
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") - $data[1]);
            $playerBankMoney->save();
        });

        $form->setTitle("§lWithdraw");
        $form->addLabel("Balance: $" . $playerBankMoney->get("Money"));
        $form->addSlider("§rSelect amount to withdraw", 1, $playerBankMoney->get("Money"));
        $form->sendtoPlayer($player);
        return $form;
    }


    public function depositForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
        $form = new SimpleForm(function (Player $player, int $data = null) use ($playerMoney){
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
                    if ($playerMoney == 0){
                        $player->sendMessage("§aYou do not have enough money to deposit into the bank");
                        return true;
                    }
                    $this->depositCustomForm($player);
            }
        });

        $form->setTitle("§lDeposit");
        $form->setContent("Balance: $" . $playerBankMoney->get("Money"));
        $form->addButton("§lDeposit All\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lDeposit Half\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§lDeposit Custom\n§r§dClick to deposit...",0,"textures/items/map_filled");
        $form->addButton("§l§cEXIT\n§r§dClick to Close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function depositCustomForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createCustomForm(function (Player $player, array $data = null) {
        $form = new CustomForm(function (Player $player, $data) {
            $result = $data;
            if ($result === null) {
                return true;
            }
            $playerMoney = EconomyAPI::getInstance()->myMoney($player);
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
            $player->sendMessage("§aYou have deposited $" . $data[1] . " into the bank");
            $playerBankMoney->set("Money", $playerBankMoney->get("Money", 0) + $data[1]);
            EconomyAPI::getInstance()->reduceMoney($player, $data[1]);
            $playerBankMoney->save();
        });

        $form->setTitle("§lDeposit");
        $form->addLabel("Balance: $" . $playerBankMoney->get("Money"));
        $form->addSlider("§rSelect amount to deposit", 1, (int) floor($playerMoney));
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
        $this->playerList[$player->getName()] = $list;

        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createCustomForm(function (Player $player, array $data = null) {
        $form = new CustomForm(function (Player $player, $data) {
            $result = $data;
            if ($result === null) {
                return true;
            }

            if (!isset($this->playerList[$player->getName()][$data[1]])){
                $player->sendMessage("§aYou must select a valid player");
                return true;
            }

            $index = $data[1];
            $playerName = $this->playerList[$player->getName()][$index];

            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
            $otherPlayerBankMoney = new Config($this->getDataFolder() . "Players/" . $playerName . ".yml", Config::YAML);
            $player->sendMessage("§aYou have transferred $" . $data[2] . " into " . $playerName . "'s bank account");
            if ($this->getServer()->getPlayer($playerName)) {
                $otherPlayer = $this->getServer()->getPlayer($playerName);
                $otherPlayer->sendMessage("§a" . $player->getName() . " has transferred $" . $data[2] . " into your bank account");
            }
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") - $data[2]);
            $otherPlayerBankMoney->set("Money", $otherPlayerBankMoney->get("Money", 0) + $data[2]);
            $playerBankMoney->save();
            $otherPlayerBankMoney->save();
        });


        $form->setTitle("§lWithdraw");
        $form->addLabel("Balance: $" . $playerBankMoney->get("Money"));
        $form->addDropdown("Select a Player", $this->playerList[$player->getName()]);
        $form->addSlider("§rSelect amount to transfer", 1, $playerBankMoney->get("Money"));
        $form->sendtoPlayer($player);
        return $form;
    }

    public static function getInstance(): BankUI {
        return self::$instance;
    }

}
