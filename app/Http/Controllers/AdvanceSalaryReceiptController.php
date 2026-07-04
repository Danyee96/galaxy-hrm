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
        $this->ensureValidSignature($request);

        $receipt = $this->receiptData($advanceSalary);
        $imageUrl = URL::temporarySignedRoute('advance-salary-receipts.image', now()->addDays(7), $advanceSalary, false);

        return response()->view('advance-salary-receipts.show', [
            'receipt' => $receipt,
            'imageUrl' => $imageUrl,
            'absoluteImageUrl' => $this->publicOrigin($request).$imageUrl,
            'pageUrl' => $request->fullUrl(),
        ]);
    }

    public function image(Request $request, AdvanceSalary $advanceSalary)
    {
        $this->ensureValidSignature($request);

        $receipt = $this->receiptData($advanceSalary);

        if (! function_exists('imagecreatetruecolor')) {
            return response($this->svgReceipt($receipt), 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $image = imagecreatetruecolor(1000, 500);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 24, 39);
        $red = imagecolorallocate($image, 220, 38, 38);

        imagefilledrectangle($image, 0, 0, 1000, 500, $white);

        $this->centerText($image, "Name: {$receipt['employee_name']}", 70, 56, $black, true, 920);
        $this->centerText($image, "Dept: {$receipt['department_name']}", 125, 50, $black, true, 920);
        $this->centerText($image, 'Advance taken', 190, 62, $black, true);
        $this->centerText($image, 'Rs '.$receipt['amount'], 260, 76, $red, true);
        $this->centerText($image, $receipt['date'], 315, 40, $black, true);

        imagesetthickness($image, 5);
        imageline($image, 190, 365, 810, 365, $black);

        $this->centerText($image, 'Total Advance taken this month:', 415, 44, $black, true, 920);
        $this->centerText($image, 'Rs '.$receipt['month_total'], 460, 58, $red, true);

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

    private function ensureValidSignature(Request $request): void
    {
        if (! URL::hasValidSignature($request) && ! URL::hasValidSignature($request, false)) {
            abort(403);
        }
    }

    private function publicOrigin(Request $request): string
    {
        $proto = $request->headers->get('x-forwarded-proto') ?: $request->getScheme();
        $host = $request->headers->get('x-forwarded-host') ?: $request->getHost();

        $proto = trim(explode(',', $proto)[0]);
        $host = trim(explode(',', $host)[0]);

        return $proto.'://'.$host;
    }

    private function centerText($image, string $text, int $y, int $size, int $color, bool $bold = false, int $maxWidth = 940): void
    {
        $font = $this->fontPath($bold);

        if ($font) {
            while ($size > 18) {
                $box = imagettfbbox($size, 0, $font, $text);
                $width = $box[2] - $box[0];

                if ($width <= $maxWidth) {
                    break;
                }

                $size -= 2;
            }

            $box = imagettfbbox($size, 0, $font, $text);
            $width = $box[2] - $box[0];
            imagettftext($image, $size, 0, (int) ((1000 - $width) / 2), $y, $color, $font, $text);
            return;
        }

        $this->centerScaledBuiltinText($image, $text, $y, $size, $color, $maxWidth);
    }

    private function centerScaledBuiltinText($image, string $text, int $y, int $size, int $color, int $maxWidth): void
    {
        $fontSize = 5;
        $sourceWidth = max(1, imagefontwidth($fontSize) * strlen($text));
        $sourceHeight = imagefontheight($fontSize);
        $scale = max(2, (int) round($size / $sourceHeight));

        while ($sourceWidth * $scale > $maxWidth && $scale > 1) {
            $scale--;
        }

        $targetWidth = $sourceWidth * $scale;
        $targetHeight = $sourceHeight * $scale;
        $source = imagecreatetruecolor($sourceWidth, $sourceHeight);
        $transparent = imagecolorallocatealpha($source, 255, 255, 255, 127);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefilledrectangle($source, 0, 0, $sourceWidth, $sourceHeight, $transparent);

        $rgba = imagecolorsforindex($image, $color);
        $sourceColor = imagecolorallocate($source, $rgba['red'], $rgba['green'], $rgba['blue']);
        imagestring($source, $fontSize, 0, 0, $text, $sourceColor);

        imagecopyresampled(
            $image,
            $source,
            (int) ((1000 - $targetWidth) / 2),
            (int) ($y - ($targetHeight / 2)),
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($source);
    }

    private function svgReceipt(array $receipt): string
    {
        $employeeName = htmlspecialchars($receipt['employee_name'], ENT_QUOTES, 'UTF-8');
        $departmentName = htmlspecialchars($receipt['department_name'], ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars($receipt['amount'], ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($receipt['date'], ENT_QUOTES, 'UTF-8');
        $monthTotal = htmlspecialchars($receipt['month_total'], ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="500" viewBox="0 0 1000 500">
  <rect width="1000" height="500" fill="#ffffff"/>
  <text x="500" y="70" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="56" font-weight="700" fill="#111827">Name: {$employeeName}</text>
  <text x="500" y="125" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="50" font-weight="700" fill="#111827">Dept: {$departmentName}</text>
  <text x="500" y="190" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="62" font-weight="700" fill="#111827">Advance taken</text>
  <text x="500" y="260" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="76" font-weight="700" fill="#dc2626">Rs {$amount}</text>
  <text x="500" y="315" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="40" font-weight="700" fill="#111827">{$date}</text>
  <line x1="190" y1="365" x2="810" y2="365" stroke="#111827" stroke-width="5"/>
  <text x="500" y="415" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="44" font-weight="700" fill="#111827">Total Advance taken this month:</text>
  <text x="500" y="460" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="58" font-weight="700" fill="#dc2626">Rs {$monthTotal}</text>
</svg>
SVG;
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
