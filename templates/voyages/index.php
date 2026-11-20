<?php
$h = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<h1>Gestion des voyages</h1>

<p>
    <a href="/">Tableau de bord</a>
</p>

<form method="GET" action="/voyages">
    <input
        type="search"
        name="q"
        placeholder="Référence, itinéraire, véhicule ou chauffeur"
        value="<?= $h($search) ?>"
    >

    <select name="status">
        <option value="">Tous les statuts</option>
        <option value="PROGRAMME" <?= $status === 'PROGRAMME' ? 'selected' : '' ?>>
            Programmé
        </option>
        <option value="EN COURS" <?= $status === 'EN COURS' ? 'selected' : '' ?>>
            En cours
        </option>
        <option value="TERMINE" <?= $status === 'TERMINE' ? 'selected' : '' ?>>
            Terminé
        </option>
        <option value="ANNULE" <?= $status === 'ANNULE' ? 'selected' : '' ?>>
            Annulé
        </option>
    </select>

    <button type="submit">Filtrer</button>
    <a href="/voyages">Réinitialiser</a>
</form>

<p>Nombre de voyages : <strong><?= count($voyages) ?></strong></p>

<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>Référence</th>
            <th>Itinéraire</th>
            <th>Date</th>
            <th>Départ</th>
            <th>Arrivée prévue</th>
            <th>Véhicule</th>
            <th>Chauffeur(s)</th>
            <th>Escales</th>
            <th>Places</th>
            <th>Statut</th>
        </tr>
    </thead>

    <tbody>
        <?php if (!$voyages): ?>
            <tr>
                <td colspan="10">Aucun voyage trouvé.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($voyages as $v): ?>
            <tr>
                <td><?= $h($v['id_voyage']) ?></td>

                <td><?= $h($v['itineraire']) ?></td>

                <td><?= $h($v['date_voyage']) ?></td>

                <td><?= $h($v['heure_depart_prevue']) ?></td>

                <td><?= $h($v['heure_arrivee_prevue']) ?></td>

                <td>
                    <?= $h($v['immatriculation']) ?><br>
                    <?= $h($v['marque']) ?>
                    <?= $h($v['modele']) ?>
                </td>

                <td>
                    <?= $h($v['chauffeurs'] ?: 'Non affecté') ?>
                </td>

                <td>
                    <?= $h($v['escales'] ?: 'Aucune') ?>
                </td>

                <td>
                    <?= (int) $v['places_prises'] ?>
                    /
                    <?= (int) $v['capacite_totale'] ?>
                    <br>
                    <small>
                        <?= (int) $v['places_libres'] ?> libre(s)
                    </small>
                </td>

                <td><?= $h($v['statut']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>