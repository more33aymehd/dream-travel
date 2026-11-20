<?php
$h = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<h1>Programmer un voyage</h1>

<p>
    <a href="/voyages">Retour aux voyages</a>
</p>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <p>
        <?= $h($_SESSION['flash_message']['message'] ?? '') ?>
    </p>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <p><?= $h($error) ?></p>
<?php endif; ?>

<form method="POST" action="/voyages/store">

    <input
        type="hidden"
        name="_csrf_token"
        value="<?= $h($csrfToken) ?>"
    >

    <fieldset>
        <legend>Informations du voyage</legend>

        <p>
            <label for="id_itineraire">Itinéraire</label><br>
            <select id="id_itineraire" name="id_itineraire" required>
                <option value="">-- Sélectionner un itinéraire --</option>

                <?php foreach ($itineraires as $itineraire): ?>
                    <option value="<?= $h($itineraire['id_itineraire']) ?>">
                        <?= $h($itineraire['libelle']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="immatriculation">Véhicule</label><br>
            <select id="immatriculation" name="immatriculation" required>
                <option value="">-- Sélectionner un véhicule --</option>

                <?php foreach ($vehicules as $vehicule): ?>
                    <option value="<?= $h($vehicule['immatriculation']) ?>">
                        <?= $h($vehicule['immatriculation']) ?>
                        -
                        <?= $h($vehicule['marque']) ?>
                        <?= $h($vehicule['modele']) ?>
                        (<?= $h($vehicule['capacite_totale']) ?> places)
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="date_voyage">Date du voyage</label><br>
            <input
                type="date"
                id="date_voyage"
                name="date_voyage"
                required
            >
        </p>

        <p>
            <label for="heure_depart_prevue">Heure de départ prévue</label><br>
            <input
                type="time"
                id="heure_depart_prevue"
                name="heure_depart_prevue"
                required
            >
        </p>

        <p>
            <label for="heure_arrivee_prevue">Heure d'arrivée prévue</label><br>
            <input
                type="time"
                id="heure_arrivee_prevue"
                name="heure_arrivee_prevue"
                required
            >
        </p>
    </fieldset>

    <p>
        <button type="submit">Programmer le voyage</button>
        <a href="/voyages">Annuler</a>
    </p>

</form>
