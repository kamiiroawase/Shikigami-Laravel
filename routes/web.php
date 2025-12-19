<?php

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

Route::get('', function () {
    $processOutput = explode("\n", Process::run('top -b -n 1')->output());

    preg_match('/%Cpu\(s\):\s*([\d.]+)\s*us,\s*([\d.]+)\s*sy,/', $processOutput[2], $cpuLineMatches);
    preg_match('/MiB Mem :\s*([\d.]+)\s*total,\s*([\d.]+)\s*free,\s*([\d.]+)\s*used,/', $processOutput[3], $memLineMatches);
    preg_match('/MiB Swap:\s*([\d.]+)\s*total,\s*([\d.]+)\s*free,\s*([\d.]+)\s*used./', $processOutput[4], $swapLineMatches);

    return 'CPU Usage: ' . (int)ceil((float)$cpuLineMatches[1] + (float)$cpuLineMatches[2]) . '%<br />'
        . 'Mem Usage: ' . (int)ceil((float)$memLineMatches[3] * 100 / (float)$memLineMatches[1]) . '%<br />'
        . 'Swap Usage: ' . (int)ceil((float)$swapLineMatches[3] * 100 / (float)$swapLineMatches[1]) . '%<br />';
});
