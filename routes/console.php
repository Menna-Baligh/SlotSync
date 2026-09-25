<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:expire')
    ->everyMinute()
    ->withoutOverlapping() 
    ->runInBackground();
