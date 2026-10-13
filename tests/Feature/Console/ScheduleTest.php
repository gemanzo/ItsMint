<?php

test('the task schedule can be listed', function () {
    // An invalid task definition (e.g. a closure passed to Schedule::exec)
    // breaks schedule:list and makes schedule:run throw when it is due.
    $this->artisan('schedule:list')->assertSuccessful();
});
