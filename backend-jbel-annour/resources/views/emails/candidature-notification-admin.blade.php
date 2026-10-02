<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle candidature</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif; color:#333333;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:6px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#ffffff; padding:28px 24px 20px; text-align:center;">
                            <img src="{{ $message->embed(public_path('images/logo-jbel-annour.png')) }}" alt="Briqueterie JBEL ANNOUR" width="360" style="display:block; margin:0 auto; width:360px; max-width:100%; height:auto; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#dd3300; height:4px; line-height:4px; font-size:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px; font-size:15px; line-height:1.6;">
                            <h2 style="margin:0 0 20px; font-size:19px; color:#111111;">Nouvelle candidature</h2>
                            <p style="margin:0 0 16px;">Une nouvelle candidature spontanée a été envoyée depuis le site. Le CV est joint à cet email.</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:0 0 24px; font-size:14px;">
                                <tr>
                                    <td colspan="2" style="background-color:#dd3300; color:#ffffff; padding:10px 12px; font-weight:bold; border:1px solid #dd3300;">Candidat</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #e0e0e0; width:40%; background-color:#fafafa; font-weight:bold;">Nom</td>
                                    <td style="padding:10px 12px; border:1px solid #e0e0e0;">{{ $candidature->nom }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #e0e0e0; width:40%; background-color:#fafafa; font-weight:bold;">Prénom</td>
                                    <td style="padding:10px 12px; border:1px solid #e0e0e0;">{{ $candidature->prenom }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #e0e0e0; width:40%; background-color:#fafafa; font-weight:bold;">Email</td>
                                    <td style="padding:10px 12px; border:1px solid #e0e0e0;">{{ $candidature->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #e0e0e0; width:40%; background-color:#fafafa; font-weight:bold;">Date</td>
                                    <td style="padding:10px 12px; border:1px solid #e0e0e0;">{{ $candidature->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            </table>

                            <p style="margin:0;">Vous pouvez répondre directement à cet email pour contacter le candidat.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#1a1a1a; padding:18px; text-align:center; font-size:12px; line-height:1.6; color:#bbbbbb;">
                            <em>La brique, notre métier. La qualité, notre engagement.</em><br>
                            &copy; {{ date('Y') }} Briqueterie JBEL ANNOUR
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
