
<?php
$h = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$messages = [
    'missing_fields' => 'Veuillez sélectionner un client et un voyage.',
    'create_error' => 'Impossible de créer la réservation. Vérifiez les données et les journaux PHP.',
];
?>

<h1>Nouvelle réservation</h1>

<p><a href="/reservations">Retour aux réservations</a></p>

<?php if (isset($messages[$_GET['status'] ?? ''])): ?>
    <p><?= $h($messages[$_GET['status']]) ?></p>
<?php endif; ?>

<?php if (!$clients): ?>
    <p>Aucun client enregistré. Créez d'abord un client.</p>
    <p><a href="/clients/create">Créer un client</a></p>
<?php elseif (!$voyages): ?>
    <p>Aucun voyage programmé avec des places disponibles.</p>
<?php else: ?>

    <form method="POST" action="/reservations/store">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">

        <fieldset>
            <legend>Informations de réservation</legend>

            <p>
                <label for="id_client">Client</label><br>
                <select name="id_client" id="id_client" required>
                    <option value="">Sélectionner un client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $h($client['id_client']) ?>">
                            <?= $h($client['prenom'] . ' ' . $client['nom']) ?>
                            — <?= $h($client['telephone']) ?>
                            (<?= $h($client['id_client']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="id_voyage">Voyage</label><br>
                <select name="id_voyage" id="id_voyage" required>
                    <option value="">Sélectionner un voyage</option>
                    <?php foreach ($voyages as $voyage): ?>
                        <option value="<?= $h($voyage['id_voyage']) ?>">
                            <?= $h($voyage['itineraire']) ?>
                            — <?= $h($voyage['date_voyage']) ?>
                            à <?= $h($voyage['heure_depart_prevue']) ?>
                            — <?= $h($voyage['classe_voyage']) ?>
                            — <?= (int) $voyage['places_libres'] ?> place(s) libre(s)
                            — <?= $h($voyage['id_voyage']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
        </fieldset>

        <p>
            La réservation sera créée avec une expiration calculée par la procédure SQL.
            La durée prévue est de 60 minutes, sans dépasser l'heure de départ.
        </p>

        <button type="submit">Créer la réservation</button>
    </form>

<?php endif; ?>
