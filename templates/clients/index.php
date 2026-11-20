
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - Dream Travel</title>
</head>
<body>

<h1>Gestion des clients</h1>

<p><a href="/">Retour au tableau de bord</a></p>

<?php if (Access::hasPermission('client.create')): ?>
    <p><a href="/clients/create">+ Ajouter un client</a></p>
<?php endif; ?>

<form action="/clients" method="GET">
    <input
        type="search"
        name="q"
        placeholder="Nom, prénom, téléphone ou identifiant"
        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
    >
    <button type="submit">Rechercher</button>
    <a href="/clients">Réinitialiser</a>
</form>

<h2>Liste des clients (<?= count($clients) ?>)</h2>

<?php if (empty($clients)): ?>
    <p>Aucun client trouvé.</p>
<?php else: ?>

<table border="1" cellpadding="8" cellspacing="0">
    <thead>
        <tr>
            <th>Identifiant</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Téléphone</th>
            <th>Date de création</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($clients as $client): ?>
            <tr>
                <td><?= htmlspecialchars($client['id_client'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($client['prenom'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($client['telephone'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($client['date_creation'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <a href="/clients/show?id=<?= urlencode($client['id_client']) ?>">
                        Consulter
                    </a>

                    <?php if (Access::hasPermission('client.update')): ?>
                        |
                        <a href="/clients/edit?id=<?= urlencode($client['id_client']) ?>">
                            Modifier
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php endif; ?>

</body>
</html>
