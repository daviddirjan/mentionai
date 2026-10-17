@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ $intro }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;font-size:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:6px 12px 6px 0;color:#71717a;width:38%;vertical-align:top;">{{ $label }}</td>
                <td style="padding:6px 0;vertical-align:top;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    @if ($body)
        <blockquote style="margin:0 0 20px;padding:12px 16px;background:#f4f4f6;border-left:3px solid #6d7cff;border-radius:6px;white-space:pre-line;">{{ $body }}</blockquote>
    @endif

    @if ($link)
        <p style="margin:0;"><a href="{{ $link }}" style="color:#4f5bf0;">{{ $link }}</a></p>
    @endif
@endsection
