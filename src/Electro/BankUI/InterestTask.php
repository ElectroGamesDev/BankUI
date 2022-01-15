<?php

namespace Electro\BankUI;

use Cassandra\Time;
use Electro\BankUI\BankUI;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class InterestTask extends Server {

    public function __construct(BankUI $plugin)
    {
        $this->plugin = $plugin;
    }

    public function getTick() : int
    {
        $this->plugin->dailyInterest();
        return false;
    }
}
