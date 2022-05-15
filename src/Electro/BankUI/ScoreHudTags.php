<?php

namespace Electro\BankUI;

use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\context\ClosureContext;
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
use Ifera\ScoreHud\event\TagsResolveEvent;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;

class ScoreHudTags implements Listener
{

    public static function updateScoreHud(Player $player, float $money)
    {
        $ev = new PlayerTagUpdateEvent(
            $player,
            new ScoreTag("bankui.money", (string)$money)
        );
        $ev->call();
    }

    public static function onTagResolve(TagsResolveEvent $event){
        $player = $event->getPlayer();
        $tag = $event->getTag();

        switch($tag->getName()){
            case "bankui.money":
                $tag->setValue(strval("$0"));
                break;
        }
    }

}