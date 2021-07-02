<?php

namespace Electro\BankUI;

use Cassandra\Time;
use Electro\BankUI\BankUI;

use pocketmine\scheduler\Task;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class InterestTask extends Task{

    public function __construct(BankUI $plugin){
        $this->plugin = $plugin;
    }

    public function onRun($tick)
    {

        $this->plugin->dailyInterest();

    }
}