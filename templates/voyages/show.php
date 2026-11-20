<?php
$h = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$idVoyage = (string) $voyage['id_voyage'];
?>

<h1>Détail du voyage <?= $h($idVoyage) ?></h1>

<p>
    <a href="/voyages">Retour à la liste des voyages</a>
</p>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <p>
        <?= $h($_SESSION['flash_message']['message'] ?? '') ?>
    </p>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<h2>Informations générales</h2>

<table>
    <tbody>
        <tr>
            <th>Identifiant</th>
            <td><?= $h($idVoyage) ?></td>
        </tr>
        <tr>
            <th>Itinéraire</th>
            <td><?= $h($detail['itineraire'] ?? $voyage['id_itineraire']) ?></td>
        </tr>
        <tr>
            <th>Véhicule</th>
            <td>
                <?= $h($voyage['immatriculation']) ?>
                -
                <?= $h($detail['marque'] ?? '') ?>
                <?= $h($detail['modele'] ?? '') ?>
            </td>
        </tr>
        <tr>
            <th>Date</th>
            <td><?= $h($voyage['date_voyage']) ?></td>
        </tr>
        <tr>
            <th>Départ prévu</th>
            <td><?= $h($voyage['heure_depart_prevue']) ?></td>
        </tr>
        <tr>
            <th>Arrivée prévue</th>
            <td><?= $h($voyage['heure_arrivee_prevue']) ?></td>
        </tr>
        <tr>
            <th>Départ réel</th>
            <td><?= $h($voyage['heure_depart_reelle'] ?? 'Non enregistré') ?></td>
        </tr>
        <tr>
            <th>Arrivée réelle</th>
            <td><?= $h($voyage['heure_arrivee_reelle'] ?? 'Non enregistrée') ?></td>
        </tr>
        <tr>
            <th>Statut</th>
            <td><strong><?= $h($voyage['statut']) ?></strong></td>
        </tr>
    </tbody>
</table>

<h2>Capacité et disponibilité</h2>

<?php if ($places): ?>
    <table>
        <tbody>
            <tr>
                <th>Capacité totale</th>
                <td><?= $h($places['capacite_totale']) ?></td>
            </tr>
            <tr>
                <th>Places prises</th>
                <td><?= $h($places['places_prises']) ?></td>
            </tr>
            <tr>
                <th>Places disponibles</th>
                <td><?= $h($places['places_libres']) ?></td>
            </tr>
        </tbody>
    </table>
<?php else: ?>
    <p>Informations de capacité indisponibles.</p>
<?php endif; ?>

<h2>Chauffeurs affectés</h2>

<?php if ($chauffeurs): ?>
    <table>
        <thead>
            <tr>
                <th>Identifiant</th>
                <th>Nom</th>
                <th>Téléphone</th>
                <th>Permis</th>
                <?php if ($canManage && $voyage['statut'] === 'PROGRAMME'): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($chauffeurs as $chauffeur): ?>
                <tr>
                    <td><?= $h($chauffeur['id_chauffeur']) ?></td>
                    <td><?= $h($chauffeur['prenom'] . ' ' . $chauffeur['nom']) ?></td>
                    <td><?= $h($chauffeur['telephone']) ?></td>
                    <td><?= $h($chauffeur['numero_permis']) ?></td>

                    <?php if ($canManage && $voyage['statut'] === 'PROGRAMME'): ?>
                        <td>
                            <form method="POST" action="/voyages/driver/remove">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="id_voyage" value="<?= $h($idVoyage) ?>">
                                <input type="hidden" name="id_chauffeur" value="<?= $h($chauffeur['id_chauffeur']) ?>">
                                <button type="submit">Retirer</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Aucun chauffeur affecté.</p>
<?php endif; ?>

<?php if ($canManage && $voyage['statut'] === 'PROGRAMME'): ?>
    <h3>Affecter un chauffeur</h3>

    <form method="POST" action="/voyages/driver/add">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <input type="hidden" name="id_voyage" value="<?= $h($idVoyage) ?>">

        <p>
            <label for="id_chauffeur">Identifiant du chauffeur</label><br>
            <input type="text" id="id_chauffeur" name="id_chauffeur" required>
        </p>

        <button type="submit">Affecter le chauffeur</button>
    </form>
<?php endif; ?>

<h2>Escales du voyage</h2>

<?php if ($escales): ?>
    <table>
        <thead>
            <tr>
                <th>Ordre</th>
                <th>Ville</th>
                <th>Escale</th>
                <th>Heure prévue</th>
                <?php if ($canManage && $voyage['statut'] === 'PROGRAMME'): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($escales as $escale): ?>
                <tr>
                    <td><?= $h($escale['ordre_passage']) ?></td>
                    <td><?= $h($escale['nom_ville']) ?></td>
                    <td><?= $h($escale['nom_escale']) ?></td>
                    <td><?= $h($escale['heure_passage_prevue'] ?? 'Non définie') ?></td>

                    <?php if ($canManage && $voyage['statut'] === 'PROGRAMME'): ?>
                        <td>
                            <form method="POST" action="/voyages/stop/remove">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="id_voyage" value="<?= $h($idVoyage) ?>">
                                <input type="hidden" name="id_escale" value="<?= $h($escale['id_escale']) ?>">
                                <button type="submit">Supprimer</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Aucune escale enregistrée.</p>
<?php endif; ?>

<?php if ($canManage && $voyage['statut'] === 'PROGRAMME'): ?>
    <h3>Ajouter une escale</h3>

    <form method="POST" action="/voyages/stop/add">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <input type="hidden" name="id_voyage" value="<?= $h($idVoyage) ?>">

        <p>
            <label for="id_escale">Identifiant de l'escale</label><br>
            <input type="text" id="id_escale" name="id_escale" required>
        </p>

        <p>
            <label for="heure_passage_prevue">Heure de passage prévue</label><br>
            <input type="time" id="heure_passage_prevue" name="heure_passage_prevue">
        </p>

        <button type="submit">Ajouter l'escale</button>
    </form>

    <?php if (count($escales) > 1): ?>
        <h3>Réorganiser les escales</h3>

        <p>Sélectionne les escales dans l'ordre de passage souhaité.</p>

        <form method="POST" action="/voyages/stop/reorder">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="id_voyage" value="<?= $h($idVoyage) ?>">

            <?php foreach ($escales as $index => $escale): ?>
                <p>
                    <label>Position <?= $index + 1 ?></label><br>
                    <select name="ordre[]" required>
                        <?php foreach ($escales as $option): ?>
                            <option
                                value="<?= $h($option['id_escale']) ?>"
                                <?= $option['id_escale'] === $escale['id_escale'] ? 'selected' : '' ?>
                            >
                                <?= $h($option['nom_ville'] . ' - ' . $option['nom_escale']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
            <?php endforeach; ?>

            <button type="submit">Enregistrer le nouvel ordre</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php if ($canManage): ?>
    <h2>Actions opérationnelles</h2>

    <?php if ($voyage['statut'] === 'PROGRAMME'): ?>
        <p>
            <a href="/voyages/edit?id=<?= urlencode($idVoyage) ?>">
                Modifier le voyage
            </a>
        </p>

        <form method="POST" action="/voyages/depart">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="id_voyage" value="<?= $h($idVoyage) ?>">
            <button type="submit">Enregistrer le départ réel</button>
        </form>

        <form method="POST" action="/voyages/cancel">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="id_voyage" value="<?= $h($idVoyage) ?>">
            <button type="submit">Annuler le voyage</button>
        </form>

    <?php elseif ($voyage['statut'] === 'EN COURS'): ?>
        <form method="POST" action="/voyages/arrive">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="id_voyage" value="<?= $h($idVoyage) ?>">
            <button type="submit">Enregistrer l'arrivée réelle</button>
        </form>

    <?php else: ?>
        <p>Aucune action opérationnelle disponible pour ce statut.</p>
    <?php endif; ?>
<?php endif; ?>
