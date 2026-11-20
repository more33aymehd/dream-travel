<?php

$statusMessages = [
    'opened' => 'La session de caisse a été ouverte avec succès.',
    'closed' => 'La session de caisse a été fermée avec succès.',
    'already_closed' => 'Cette session est déjà fermée.',
    'close_error' => 'Impossible de fermer la session. Vérifiez les conditions de fermeture.',
];

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail de la caisse</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f5f6fa;
            color: #222;
        }
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
            background: #e5f5e9;
            border-radius: 5px;
        }
        .warning {
            padding: 12px;
            background: #fff3cd;
            border-radius: 5px;
        }
        button {
            padding: 10px 15px;
            background: #b42318;
            color: white;
            border: 0;
            border-radius: 5px;
            cursor: pointer;
        }
        .muted { color: #666; }
    </style>
</head>
<body>

<h1>Détail de la session de caisse</h1>

<p><a href="/cash">Retour aux caisses</a></p>

<?php if (isset($statusMessages[$status])): ?>
    <p class="<?= $status === 'close_error' || $status === 'already_closed' ? 'warning' : 'message' ?>">
        <?= htmlspecialchars($statusMessages[$status], ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>

<div class="card">
    <h2>Informations générales</h2>

    <p>
        <strong>Identifiant :</strong>
        <?= htmlspecialchars($session['id_session'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        <strong>Agence :</strong>
        <?= htmlspecialchars($session['id_agence'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        <strong>Employé :</strong>
        <?= htmlspecialchars($session['prenom'] . ' ' . $session['nom'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        <strong>Date d'ouverture :</strong>
        <?= htmlspecialchars($session['date_heure_ouverture'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        <strong>Date de fermeture :</strong>
        <?= htmlspecialchars($session['date_heure_fermeture'] ?? 'Non fermée', ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        <strong>Total encaissé :</strong>
        <?= number_format((int) $session['total_encaisse_fcfa'], 0, ',', ' ') ?> FCFA
    </p>

    <p>
        <strong>Statut :</strong>

        <?php if ($session['statut_session'] === 'OUVERTE'): ?>
            <span class="open">OUVERTE</span>
        <?php else: ?>
            <span class="closed">FERMÉE</span>
        <?php endif; ?>
    </p>

    <?php if ($canClose): ?>
        <form method="POST" action="/cash/close"
              onsubmit="return confirm('Confirmer la fermeture de cette caisse ?');">
            <input type="hidden" name="_csrf_token"
                   value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_session"
                   value="<?= htmlspecialchars($session['id_session'], ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Fermer ma caisse</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($canViewPayments): ?>
    <div class="card">
        <h2>Historique des paiements</h2>

        <?php if (empty($payments)): ?>
            <p>Aucun paiement enregistré dans cette session.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Réservation</th>
                        <th>Montant</th>
                        <th>Moyen de paiement</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars($payment['id_paiement'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($payment['id_reservation'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format((int) $payment['montant_fcfa'], 0, ',', ' ') ?> FCFA</td>
                            <td><?= htmlspecialchars($payment['moyen_paiement'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($payment['date_heure_paiement'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($payment['statut_paiement'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <p class="muted">
            Le détail des paiements est réservé aux utilisateurs disposant des autorisations financières correspondantes.
        </p>
    </div>
<?php endif; ?>

</body>
</html>
