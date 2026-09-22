<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('comments:poll')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
