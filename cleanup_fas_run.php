<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$runs = App\Models\FasExecutionRun::where('execution_type', 'DAILY_ANALYSIS')
    ->where('status', 'RUNNING')
    ->orderByDesc('id')
    ->get();

if ($runs->isEmpty()) {
    echo "No running daily runs found.\n";
    exit(0);
}

foreach ($runs as $run) {
    echo json_encode([
        'id' => $run->id,
        'status' => $run->status,
        'current_step' => $run->current_step,
        'updated_at' => optional($run->updated_at)->toDateTimeString(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;

    $run->update([
        'status' => 'PENDING',
        'current_step' => null,
        'started_at' => null,
        'finished_at' => null,
        'errors' => null,
    ]);

    echo "Reset run #{$run->id} to PENDING.\n";
}
