<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('omiquiz:tick')->everySecond()->withoutOverlapping(1);
