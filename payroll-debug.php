<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\PayrollRun;
$run = PayrollRun::with('payrollEntries.employee.employee')->first();
if (! $run) {
    echo "NO RUN\n";
    exit(0);
}
echo json_encode($run->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
