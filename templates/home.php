<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dream Travel</title>
</head>
<body>
    <h1>Dream Travel</h1>
    <p>Plateforme de gestion de transport et de voyages.</p>
	<?php if ($user !== null): ?>
    <h2>Utilisateur connecté</h2>

    <p>
        Nom :
        <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        Email :
        <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        Agence :
        <?= htmlspecialchars($user['id_agence'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>Rôles :</p>
    <ul>
        <?php foreach ($user['roles'] as $role): ?>
            <li><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>

    <p>Portées :</p>
    <ul>
        <?php foreach ($user['portees'] as $portee): ?>
            <li><?= htmlspecialchars($portee, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
	<form action="/logout" method="POST">
    <input
        type="hidden"
        name="_csrf_token"
        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    >
    <button type="submit">Se déconnecter</button>
</form>
<?php endif; ?>
    <h2>État du système</h2>
    <p>Base de données : <?= htmlspecialchars($databaseStatus, ENT_QUOTES, 'UTF-8') ?></p>
    <p>Base utilisée : <?= htmlspecialchars($databaseName, ENT_QUOTES, 'UTF-8') ?></p>
</body>
</html>