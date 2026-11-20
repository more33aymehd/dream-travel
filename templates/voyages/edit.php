<?php
$h = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<h1>Modifier le voyage <?= $h($voyage['id_voyage']) ?></h1>

<p><a href="/voyages/show?id=<?= urlencode($voyage['id_voyage']) ?>">Retour au voyage</a></p>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <p><?= $h($_SESSION['flash_message']['message'] ?? '') ?></p>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <p><?= $h($error) ?></p>
<?php endif; ?>

<form method="POST" action="/voyages/update">
    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
    <input type="hidden" name="id_voyage" value="<?= $h($voyage['id_voyage']) ?>">

    <fieldset>
        <legend>Informations du voyage</legend>

        <p>
            <label for="id_itineraire">Itinéraire</label><br>
            <select id="id_itineraire" name="id_itineraire" required>
                <?php foreach ($itineraires as $itineraire): ?>
                    <option
                        value="<?= $h($itineraire['id_itineraire']) ?>"
                        <?= $itineraire['id_itineraire'] === $voyage['id_itineraire'] ? 'selected' : '' ?>
                    >
                        <?= $h($itineraire['libelle']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="immatriculation">Véhicule</label><br>
            <select id="immatriculation" name="immatriculation" required>
                <?php foreach ($vehicules as $vehicule): ?>
                    <option
                        value="<?= $h($vehicule['immatriculation']) ?>"
                        <?= $vehicule['immatriculation'] === $voyage['immatriculation'] ? 'selected' : '' ?>
                    >
                        <?= $h($vehicule['immatriculation']) ?>
                        - <?= $h($vehicule['marque']) ?>
                        <?= $h($vehicule['modele']) ?>
                        (<?= $h($vehicule['capacite_totale']) ?> places)
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="date_voyage">Date</label><br>
            <input
                type="date"
                id="date_voyage"
                name="date_voyage"
                value="<?= $h($voyage['date_voyage']) ?>"
                required
            >
        </p>

        <p>
            <label for="heure_depart_prevue">Heure de départ prévue</label><br>
            <input
                type="time"
                id="heure_depart_prevue"
                name="heure_depart_prevue"
                value="<?= $h(substr((string) $voyage['heure_depart_prevue'], 0, 5)) ?>"
                required
            >
        </p>

        <p>
            <label for="heure_arrivee_prevue">Heure d'arrivée prévue</label><br>
            <input
                type="time"
                id="heure_arrivee_prevue"
                name="heure_arrivee_prevue"
                value="<?= $h(substr((string) $voyage['heure_arrivee_prevue'], 0, 5)) ?>"
                required
            >
        </p>
    </fieldset>

    <p>
        <button type="submit">Enregistrer les modifications</button>
        <a href="/voyages/show?id=<?= urlencode($voyage['id_voyage']) ?>">Annuler</a>
    </p>
</form>
PHPcat > templates/voyages/edit.php <<'PHP'
<?php
$h = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<h1>Modifier le voyage <?= $h($voyage['id_voyage']) ?></h1>

<p><a href="/voyages/show?id=<?= urlencode($voyage['id_voyage']) ?>">Retour au voyage</a></p>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <p><?= $h($_SESSION['flash_message']['message'] ?? '') ?></p>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <p><?= $h($error) ?></p>
<?php endif; ?>

<form method="POST" action="/voyages/update">
    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
    <input type="hidden" name="id_voyage" value="<?= $h($voyage['id_voyage']) ?>">

    <fieldset>
        <legend>Informations du voyage</legend>

        <p>
            <label for="id_itineraire">Itinéraire</label><br>
            <select id="id_itineraire" name="id_itineraire" required>
                <?php foreach ($itineraires as $itineraire): ?>
                    <option
                        value="<?= $h($itineraire['id_itineraire']) ?>"
                        <?= $itineraire['id_itineraire'] === $voyage['id_itineraire'] ? 'selected' : '' ?>
                    >
                        <?= $h($itineraire['libelle']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="immatriculation">Véhicule</label><br>
            <select id="immatriculation" name="immatriculation" required>
                <?php foreach ($vehicules as $vehicule): ?>
                    <option
                        value="<?= $h($vehicule['immatriculation']) ?>"
                        <?= $vehicule['immatriculation'] === $voyage['immatriculation'] ? 'selected' : '' ?>
                    >
                        <?= $h($vehicule['immatriculation']) ?>
                        - <?= $h($vehicule['marque']) ?>
                        <?= $h($vehicule['modele']) ?>
                        (<?= $h($vehicule['capacite_totale']) ?> places)
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="date_voyage">Date</label><br>
            <input
                type="date"
                id="date_voyage"
                name="date_voyage"
                value="<?= $h($voyage['date_voyage']) ?>"
                required
            >
        </p>

        <p>
            <label for="heure_depart_prevue">Heure de départ prévue</label><br>
            <input
                type="time"
                id="heure_depart_prevue"
                name="heure_depart_prevue"
                value="<?= $h(substr((string) $voyage['heure_depart_prevue'], 0, 5)) ?>"
                required
            >
        </p>

        <p>
            <label for="heure_arrivee_prevue">Heure d'arrivée prévue</label><br>
            <input
                type="time"
                id="heure_arrivee_prevue"
                name="heure_arrivee_prevue"
                value="<?= $h(substr((string) $voyage['heure_arrivee_prevue'], 0, 5)) ?>"
                required
            >
        </p>
    </fieldset>

    <p>
        <button type="submit">Enregistrer les modifications</button>
        <a href="/voyages/show?id=<?= urlencode($voyage['id_voyage']) ?>">Annuler</a>
    </p>
</form>
