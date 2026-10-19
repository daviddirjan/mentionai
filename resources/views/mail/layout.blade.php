<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f2f0eb;font-family:Afacad,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1b3030;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dcdcd5;">
                    <tr>
                        <td style="padding:20px 28px;background:#1b3030;color:#ffffff;font-size:18px;font-weight:600;letter-spacing:-0.01em;">
                            MentionAI <span style="color:#21d789;">&#9679;</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;background:#fbfaf7;border-top:1px solid #e6e4dd;font-size:12px;color:#5b6b6b;">
                            © MentionAI. Developed by Axiontic SRL.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
