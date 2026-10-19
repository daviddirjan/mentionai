@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ $intro }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;font-size:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:6px 12px 6px 0;color:#5b6b6b;width:38%;vertical-align:top;">{{ $label }}</td>
                <td style="padding:6px 0;vertical-align:top;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    @if ($body)
        <blockquote style="margin:0 0 20px;padding:12px 16px;background:#f2f0eb;border-left:3px solid #21d789;border-radius:6px;white-space:pre-line;">{{ $body }}</blockquote>
    @endif

    @if ($link)
        <p style="margin:0;"><a href="{{ $link }}" style="color:#1b3030;">{{ $link }}</a></p>
    @endif
@endsection
