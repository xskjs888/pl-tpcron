<?php

use pl125\cron\command\Run;
use pl125\cron\command\Schedule;

\think\Console::addDefaultCommands([
    Run::class,
    Schedule::class
]);