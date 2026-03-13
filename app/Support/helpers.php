<?php

if (!function_exists('dispatch_now')) {
    function dispatch_now($job, $handler = null)
    {
        return dispatch_sync($job, $handler);
    }
}
