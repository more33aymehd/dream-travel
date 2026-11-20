<?php
$statusMessages = [
    'open_error' => 'Impossible d’ouvrir la caisse. Vérifiez vos droits et votre situation.',
    'missing_session' => 'Aucune session sélectionnée.',
];

$status = $_GET['status'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des caisses</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f5f6fa;
            color: #222;
        }
        h1 { margin-bottom: 10px; }
        .card {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 6px #0001;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th { background: #eee; }
        .open { color: #16803c; font-weight: bold; }
        .closed { color: #777; font-weight: bold; }
        .message {
            padding: 12px;
            background: #fff3cd;
            border-radius: 5px;
        }
        button, .link {
            display: inline-block;
            padding: 10px 15px;
            background: #174ea6;
            color: white;
            border: 0;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
        }
        .muted { color: #666; }
    </style>
</head>
<body>

<h1>Gestion des caisses</h1>
<p class="muted">
    Connecté : <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom'], ENT_QUOTES, 'UTF-8') ?>
</p>

<p><a href="/">Retour à l'accueil</a></p>

<?php if (isset($statusMessages[$status])): ?>
    <p class="message">
        <?= htmlspecialchars($statusMessages[$status], ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>

<div class="card">
    <h2>Ma session de caisse</h2>

    <?php if ($currentSession): ?>
        <p>
            Session :
            <strong><?= htmlspecialchars($currentSession['id_session'], ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
        <p>
            Ouverture :
            <?= htmlspecialchars($currentSession['date_heure_ouverture'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p>
            Statut : <span class="open">OUVERTE</span>
        </p>
        <p>
            Total encaissé :
            <strong><?= number_format((int) $currentSession['total_encaisse_fcfa'], 0, ',', ' ') ?> FCFA</strong>
        </p>
        <a class="link"
           href="/cash/show?id=<?= rawurlencode($currentSession['id_session']) ?>">
            Consulter ma session
        </a>
    <?php else: ?>
        <p>Aucune session de caisse ouverte pour votre compte.</p>

        <?php if ($canOpen): ?>
            <form method="POST" action="/cash/open">
                <input type="hidden" name="_csrf_token"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Ouvrir ma caisse</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Historique des sessions</h2>

    <?php if (empty($sessions)): ?>
        <p>Aucune session accessible.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Employé</th>
                    <th>Agence</th>
                    <th>Ouverture</th>
                    <th>Fermeture</th>
                    <th>Total encaissé</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?= htmlspecialchars($session['id_session'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?= htmlspecialchars($session['prenom'] . ' ' . $session['nom'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td><?= htmlspecialchars($session['id_agence'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($session['date_heure_ouverture'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?= htmlspecialchars($session['date_heure_fermeture'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                        <?= number_format((int) $session['total_encaisse_fcfa'], 0, ',', ' ') ?> FCFA
                    </td>
                    <td>
                        <?php if ($session['statut_session'] === 'OUVERTE'): ?>
                            <span class="open">OUVERTE</span>
                        <?php else: ?>
                            <span class="closed">FERMÉE</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/cash/show?id=<?= rawurlencode($session['id_session']) ?>">
                            Détails
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
