<?php

namespace App\Services\Audit;

use App\Contracts\AuditReportBuilder;
use App\Contracts\ReportRenderer;
use App\Models\Analysis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/** Renders the `pdf.geo-audit` view to reports/{uuid}.pdf on the private local disk. */
class DompdfReportRenderer implements ReportRenderer
{
    public const VIEW = 'pdf.geo-audit';

    public function __construct(private readonly AuditReportBuilder $builder) {}

    public function render(Analysis $analysis): string
    {
        $report = $this->builder->build($analysis);

        $fonts = storage_path('fonts');
        File::ensureDirectoryExists($fonts);

        // No remote fetches: the PDF must never load URLs that came from the analysed site or AI answers.
        $pdf = Pdf::setOption([
            'isRemoteEnabled' => false,
            'chroot' => base_path(),
            'fontDir' => $fonts,
            'fontCache' => $fonts,
        ])->loadView(self::VIEW, ['report' => $report])->setPaper('a4');

        $path = "reports/{$analysis->uuid}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $analysis->forceFill(['report_path' => $path, 'report_generated_at' => now()])->save();

        return $path;
    }
}
