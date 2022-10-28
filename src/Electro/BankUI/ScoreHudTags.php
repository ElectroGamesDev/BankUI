<?php

namespace Electro\BankUI;

use pocketmine\player\Player;
use pocketmine\event\Listener;
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
