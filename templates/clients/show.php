
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche client - Dream Travel</title>
</head>
<body>

<h1>Fiche client</h1>

<p><a href="/clients">Retour à la liste</a></p>

<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>Identifiant</th>
        <td><?= htmlspecialchars($client['id_client'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <th>Nom</th>
        <td><?= htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <th>Prénom</th>
        <td><?= htmlspecialchars($client['prenom'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <th>Téléphone</th>
        <td><?= htmlspecialchars($client['telephone'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <th>Type de pièce</th>
        <td><?= htmlspecialchars($client['type_piece_identite'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <th>Numéro de pièce</th>
        <td><?= htmlspecialchars($client['numero_piece_identite'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <th>Date de création</th>
        <td><?= htmlspecialchars($client['date_creation'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
</table>

<?php if (Access::hasPermission('client.update')): ?>
    <p>
        <a href="/clients/edit?id=<?= urlencode($client['id_client']) ?>">
            Modifier ce client
        </a>
    </p>
<?php endif; ?>

</body>
</html>
