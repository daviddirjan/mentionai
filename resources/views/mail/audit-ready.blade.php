@extends('mail.layout')

@section('content')
    @if ($isDemo)
        <p style="margin:0 0 16px;padding:8px 12px;background:#fbf3e2;border:1px solid #e9cf95;border-radius:8px;color:#8a5d06;font-size:13px;font-weight:600;">{{ __('site.results.demo_banner') }}</p>
    @endif

    <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:0.12em;color:#5b6b6b;">{{ __('site.results.audit.badge') }}</p>
    <p style="margin:0 0 20px;font-size:22px;line-height:1.25;font-weight:600;color:#1b3030;">{{ $domain }}</p>
    <p style="margin:0 0 24px;">{{ __('site.mail.audit_ready_intro', ['domain' => $domain, 'tests' => $tests]) }}</p>

    <p style="margin:0 0 12px;">
        <a href="{{ $reportUrl }}" style="display:inline-block;padding:12px 22px;background:#1b3030;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;">{{ __('site.mail.audit_download') }}</a>
    </p>
    <p style="margin:0 0 24px;font-size:13px;color:#5b6b6b;">{{ __('site.mail.audit_link_expiry', ['days' => $days]) }}</p>

    <p style="margin:0 0 24px;"><a href="{{ $resultsUrl }}" style="color:#1b3030;text-decoration:underline;text-decoration-color:#21d789;">{{ __('site.mail.audit_view') }}</a></p>

    <p style="margin:0;">{{ __('site.mail.signoff') }}</p>
@endsection
