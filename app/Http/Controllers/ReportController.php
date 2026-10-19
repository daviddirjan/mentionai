<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * The PDF is personal: it needs the signed link from the email, or the session flag set when
     * the visitor left their email for this audit. The audit page itself stays public by uuid.
     */
    public function download(Request $request, Analysis $analysis): StreamedResponse
    {
        abort_unless($request->hasValidSignature() || $request->session()->get("audit_access.{$analysis->uuid}") === true, 403);

        $disk = Storage::disk('local');
        abort_unless($analysis->isAudit() && $analysis->hasReport() && $disk->exists($analysis->report_path), 404);

        $filename = sprintf('geo-audit-%s-%s.pdf', preg_replace('/[^a-z0-9.-]/', '', strtolower((string) $analysis->domain)), $analysis->report_generated_at->format('Y-m-d'));

        return $disk->download($analysis->report_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
