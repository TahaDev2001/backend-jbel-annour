<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre candidature a bien été reçue</title>
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
                            <h2 style="margin:0 0 20px; font-size:19px; color:#111111;">Candidature bien reçue</h2>
                            <p style="margin:0 0 16px;">Bonjour <strong>{{ $candidature->prenom }} {{ $candidature->nom }}</strong>,</p>
                            <p style="margin:0 0 16px;">
                                Nous vous confirmons la bonne réception de votre candidature spontanée.
                                Notre équipe va l'étudier avec attention et reviendra vers vous si votre profil correspond à nos besoins.
                            </p>
                            <p style="margin:24px 0 0;">
                                Cordialement,<br>
                                <strong style="color:#dd3300;">L'équipe Briqueterie JBEL ANNOUR</strong>
                            </p>
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
