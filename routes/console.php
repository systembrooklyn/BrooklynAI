<?php

use App\Modules\Execution\Console\Commands\PollGmailCommand;
use App\Modules\Execution\Console\Commands\RecoverFailedPollExecutionsCommand;
use App\Modules\Execution\Console\Commands\RunScheduledWorkflowsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(RunScheduledWorkflowsCommand::class)
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command(PollGmailCommand::class)
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command(RecoverFailedPollExecutionsCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
