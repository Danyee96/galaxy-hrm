<?php

namespace App\Http\Controllers;

use App\Models\AdvanceSalary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AdvanceSalaryReceiptController extends Controller
{
    public function show(Request $request, AdvanceSalary $advanceSalary)
    {
        $receipt = $this->receiptData($advanceSalary);
        $imageUrl = URL::temporarySignedRoute('advance-salary-receipts.image', now()->addDays(7), $advanceSalary);

        return response()->view('advance-salary-receipts.show', [
            'receipt' => $receipt,
            'imageUrl' => $imageUrl,
            'pageUrl' => $request->fullUrl(),
        ]);
    }

    public function image(AdvanceSalary $advanceSalary)
    {
        $receipt = $this->receiptData($advanceSalary);
        $image = imagecreatetruecolor(1000, 500);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 24, 39);
        $red = imagecolorallocate($image, 220, 38, 38);

        imagefilledrectangle($image, 0, 0, 1000, 500, $white);

        $this->centerText($image, "Name: {$receipt['employee_name']}", 80, 40, $black);
        $this->centerText($image, "Dept: {$receipt['department_name']}", 125, 36, $black);
        $this->centerText($image, 'Advance taken', 190, 52, $black, true);
        $this->centerText($image, 'Rs '.$receipt['amount'], 260, 60, $red, true);
        $this->centerText($image, $receipt['date'], 315, 30, $black, true);

        imagesetthickness($image, 5);
        imageline($image, 190, 365, 810, 365, $black);

        $this->centerText($image, 'Total Advance taken this month:', 415, 31, $black, true);
        $this->centerText($image, 'Rs '.$receipt['month_total'], 460, 40, $red, true);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function receiptData(AdvanceSalary $advanceSalary): array
    {
        $advanceSalary->loadMissing('employee.employee.department');

        $date = $advanceSalary->request_date ?: now();
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $monthTotal = AdvanceSalary::where('employee_id', $advanceSalary->employee_id)
            ->whereBetween('request_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        return [
            'employee_name' => $advanceSalary->employee?->name ?: '-',
            'department_name' => $advanceSalary->employee?->employee?->department?->name ?: 'Unassigned',
            'amount' => number_format((float) $advanceSalary->amount),
            'date' => Carbon::parse($date)->format('j F Y'),
            'month_total' => number_format((float) $monthTotal),
            'notes' => $advanceSalary->notes,
        ];
    }

    private function centerText($image, string $text, int $y, int $size, int $color, bool $bold = false): void
    {
        $font = $this->fontPath($bold);

        if ($font) {
            $box = imagettfbbox($size, 0, $font, $text);
            $width = $box[2] - $box[0];
            imagettftext($image, $size, 0, (int) ((1000 - $width) / 2), $y, $color, $font, $text);
            return;
        }

        $fontSize = 5;
        $width = imagefontwidth($fontSize) * strlen($text);
        imagestring($image, $fontSize, (int) ((1000 - $width) / 2), $y - 12, $text, $color);
    }

    private function fontPath(bool $bold): ?string
    {
        $paths = $bold
            ? [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/Library/Fonts/Arial Bold.ttf',
            ]
            : [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/Library/Fonts/Arial.ttf',
            ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
