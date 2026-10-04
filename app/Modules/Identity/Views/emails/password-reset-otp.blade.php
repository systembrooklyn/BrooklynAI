@php
    $isRtl = app()->getLocale() === 'ar';
    $dir = $isRtl ? 'rtl' : 'ltr';
    $align = $isRtl ? 'right' : 'left';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('identity::messages.password_reset_email_subject') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1f2937;">

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f5f7; padding: 32px 16px;">
        <tr>
            <td align="center">

                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#0f172a; padding: 24px 32px; text-align: {{ $align }};">
                            <span style="color:#ffffff; font-size:18px; font-weight:700; letter-spacing:0.3px;">BrooklynAI</span>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px 32px 24px 32px; text-align: {{ $align }};">

                            <h1 style="margin:0 0 16px 0; font-size:22px; line-height:1.4; color:#0f172a; font-weight:600;">
                                {{ __('identity::messages.password_reset_email_greeting', ['name' => $name]) }}
                            </h1>

                            <p style="margin:0 0 24px 0; font-size:15px; line-height:1.6; color:#4b5563;">
                                {{ __('identity::messages.password_reset_email_line_1') }}
                            </p>

                            {{-- OTP box --}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 8px 0 24px 0;">
                                <tr>
                                    <td align="center" style="background-color:#f1f5f9; border:1px dashed #cbd5e1; border-radius:10px; padding: 24px;">
                                        <div style="font-size:12px; font-weight:600; color:#64748b; letter-spacing:1px; text-transform:uppercase; margin-bottom:8px;">
                                            {{ __('identity::messages.password_reset_email_code_label') }}
                                        </div>
                                        <div style="font-size:36px; font-weight:700; letter-spacing:10px; color:#0f172a; font-family: 'Courier New', Courier, monospace; direction:ltr; unicode-bidi: embed;">
                                            {{ $code }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px 0; font-size:14px; line-height:1.6; color:#64748b;">
                                {{ __('identity::messages.password_reset_email_expiry', ['minutes' => $expiryMinutes]) }}
                            </p>

                            <p style="margin:0; font-size:14px; line-height:1.6; color:#64748b;">
                                {{ __('identity::messages.password_reset_email_line_2') }}
                            </p>
                        </td>
                    </tr>

                    {{-- Divider --}}
                    <tr>
                        <td style="padding: 0 32px;">
                            <div style="height:1px; background-color:#e5e7eb;"></div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 20px 32px 28px 32px; text-align: {{ $align }};">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#9ca3af;">
                                &copy; {{ date('Y') }} BrooklynAI. {{ __('identity::messages.password_reset_email_footer') }}
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
