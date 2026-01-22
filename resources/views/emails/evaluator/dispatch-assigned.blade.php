<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bourse UBORA – Attribution de dossiers</title>
</head>
<body style="font-family: Arial, sans-serif; color: #000;">

<p>Bonjour <strong>{{ $evaluatorName }}</strong>,</p>

<p>
    Vous avez été désigné(e) comme <strong>{{ $roleLabel }}</strong> pour les phases 
    <strong>Bourse UBORA – Orange RDC</strong> (édition {{ $periodLabel }}).
</p>

<p>
    <strong>{{ $assignedCount }}</strong> dossier(s) de candidature vous ont été attribué(s) pour évaluation.
</p>

<p>
    Veuillez vous connecter à la plateforme pour consulter les dossiers :
</p>

<p>
    <a href="{{ $loginUrl }}" style="color: #FF7900; font-weight: bold;">
        Accéder à la plateforme UBORA
    </a>
</p>

<p>
    Votre contribution est essentielle pour garantir la qualité et l’équité du processus
    de sélection.
</p>

<p>
    Cordialement,<br>
    L’équipe Bourse UBORA<br>
    Orange RDC
</p>

<hr>

<p style="font-size: 12px; color: #666;">
    Ceci est un message automatique envoyé par la plateforme Bourse UBORA.<br>
    Merci de ne pas répondre à cet e-mail.
</p>

</body>
</html>
