@extends('mail.layout')

@section('content')
    <p style="margin:0 0 12px;">{{ __('site.mail.greeting', ['name' => $name]) }}</p>
    <p style="margin:0 0 20px;">{{ $intro }}</p>

    @if ($quote)
        <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;color:#5b6b6b;">{{ __('site.mail.your_message') }}</p>
        <blockquote style="margin:0 0 20px;padding:12px 16px;background:#f2f0eb;border-left:3px solid #21d789;border-radius:6px;white-space:pre-line;">{{ $quote }}</blockquote>
    @endif

    @if ($link)
        <p style="margin:0 0 20px;"><a href="{{ $link }}" style="display:inline-block;padding:10px 18px;background:#1b3030;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;">{{ __('site.mail.view_analysis') }}</a></p>
    @endif

    <p style="margin:0;">{{ __('site.mail.signoff') }}</p>
@endsection
