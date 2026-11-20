
<?php
$h = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$messages = [
    'created' => 'Réservation créée avec succès.',
    'passenger_added' => 'Voyageur ajouté avec succès.',
    'paid' => 'Paiement enregistré. Les billets ont été émis automatiquement.',
    'cancelled' => 'Réservation annulée. Les places ont été libérées.',
    'invalid_passenger' => 'Les informations du voyageur sont invalides.',
    'invalid_identity' => 'Le type et le numéro de pièce doivent être renseignés ensemble.',
    'invalid_seat' => 'Le numéro de place est invalide.',
    'passenger_error' => 'Impossible d’ajouter le voyageur. Vérifiez la disponibilité de la place et le tarif.',
    'invalid_payment' => 'Les informations de paiement sont invalides.',
    'invalid_cash_session' => 'Aucune session de caisse ouverte et autorisée pour cette agence.',
    'payment_error' => 'Le paiement a échoué. Vérifiez la session, le statut et les informations de paiement.',
    'cancel_error' => 'Impossible d’annuler cette réservation.',
];

$pending = $reservation['statut'] === 'EN ATTENTE';
$notExpired = strtotime($reservation['date_expiration']) > time();
$canModify = $pending && $notExpired;
?>

<h1>Détail de la réservation</h1>

<p>
    <a href="/reservations">Retour à la liste</a>
</p>

<?php if (isset($messages[$status])): ?>
    <p><?= $h($messages[$status]) ?></p>
<?php endif; ?>

<fieldset>
    <legend>Réservation</legend>

    <p><strong>Référence :</strong> <?= $h($reservation['id_reservation']) ?></p>
    <p><strong>Statut :</strong> <?= $h($reservation['statut']) ?></p>
    <p><strong>Date de création :</strong> <?= $h($reservation['date_heure_reservation']) ?></p>
    <p><strong>Date d'expiration :</strong> <?= $h($reservation['date_expiration']) ?></p>
    <p>
        <strong>Montant total :</strong>
        <?= number_format((int) $reservation['montant_total_fcfa'], 0, ',', ' ') ?> FCFA
    </p>
</fieldset>

<fieldset>
    <legend>Client</legend>

    <p>
        <strong>Nom :</strong>
        <?= $h($reservation['client_prenom']) ?>
        <?= $h($reservation['client_nom']) ?>
    </p>
    <p><strong>Téléphone :</strong> <?= $h($reservation['client_telephone']) ?></p>
    <p><strong>Pièce :</strong> <?= $h($reservation['client_type_piece'] ?? 'Non renseignée') ?></p>
    <p><strong>Numéro :</strong> <?= $h($reservation['client_numero_piece'] ?? 'Non renseigné') ?></p>
</fieldset>

<fieldset>
    <legend>Voyage</legend>

    <p><strong>Itinéraire :</strong> <?= $h($reservation['itineraire']) ?></p>
    <p><strong>Date :</strong> <?= $h($reservation['date_voyage']) ?></p>
    <p><strong>Départ :</strong> <?= $h($reservation['heure_depart_prevue']) ?></p>
    <p><strong>Arrivée prévue :</strong> <?= $h($reservation['heure_arrivee_prevue']) ?></p>
    <p>
        <strong>Véhicule :</strong>
        <?= $h($reservation['marque']) ?>
        <?= $h($reservation['modele']) ?>
        (<?= $h($reservation['immatriculation']) ?>)
    </p>
</fieldset>

<h2>Voyageurs</h2>

<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>N°</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Pièce</th>
            <th>Numéro de pièce</th>
            <th>Place</th>
            <th>Montant</th>
            <th>Actif</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$voyageurs): ?>
            <tr><td colspan="8">Aucun voyageur ajouté.</td></tr>
        <?php endif; ?>

        <?php foreach ($voyageurs as $v): ?>
            <tr>
                <td><?= (int) $v['numero_voyageur'] ?></td>
                <td><?= $h($v['nom_voyageur']) ?></td>
                <td><?= $h($v['prenom_voyageur']) ?></td>
                <td><?= $h($v['type_piece'] ?? '—') ?></td>
                <td><?= $h($v['numero_piece'] ?? '—') ?></td>
                <td><?= $h($v['numero_place'] ?? 'Non attribuée') ?></td>
                <td><?= number_format((int) $v['montant_fcfa'], 0, ',', ' ') ?> FCFA</td>
                <td><?= (int) $v['actif'] === 1 ? 'Oui' : 'Non' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($canModify && Access::hasPermission('reservation.create')): ?>
    <h2>Ajouter un voyageur</h2>

    <form method="POST" action="/reservations/passenger">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <input type="hidden" name="id_reservation" value="<?= $h($reservation['id_reservation']) ?>">

        <p>
            <label>Nom</label><br>
            <input type="text" name="nom" maxlength="100" required>
        </p>

        <p>
            <label>Prénom</label><br>
            <input type="text" name="prenom" maxlength="100" required>
        </p>

        <p>
            <label>Type de pièce</label><br>
            <select name="type_piece">
                <option value="">Aucune pièce renseignée</option>
                <option value="CNI">CNI</option>
                <option value="PASSEPORT">Passeport</option>
                <option value="PERMIS">Permis</option>
                <option value="CARTE SEJOUR">Carte de séjour</option>
            </select>
        </p>

        <p>
            <label>Numéro de pièce</label><br>
            <input type="text" name="numero_piece" maxlength="50">
        </p>

        <p>
            <label>Catégorie tarifaire</label><br>
            <select name="categorie_tarif" required>
                <option value="STANDARD">Standard</option>
                <option value="VIP">VIP</option>
                <option value="ETUDIANT">Étudiant</option>
            </select>
        </p>

        <p>
            <label>Numéro de place (laisser vide pour attribution automatique)</label><br>
            <input type="number" name="numero_place" min="1">
        </p>

        <button type="submit">Ajouter le voyageur</button>
    </form>
<?php endif; ?>

<?php if ($canModify && Access::hasPermission('reservation.cancel.pending')): ?>
    <h2>Annuler la réservation</h2>

    <form method="POST" action="/reservations/cancel"
          onsubmit="return confirm('Confirmer l’annulation de cette réservation ?');">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <input type="hidden" name="id_reservation" value="<?= $h($reservation['id_reservation']) ?>">
        <button type="submit">Annuler la réservation</button>
    </form>
<?php endif; ?>

<?php if ($canModify && Access::hasPermission('payment.collect')): ?>
    <h2>Encaisser la réservation</h2>

    <?php if (!$voyageurs): ?>
        <p>Ajoutez au moins un voyageur avant l'encaissement.</p>
    <?php elseif (!$sessions): ?>
        <p>Aucune session de caisse ouverte dans l'agence de cette réservation.</p>
    <?php else: ?>
        <form method="POST" action="/reservations/pay">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="id_reservation" value="<?= $h($reservation['id_reservation']) ?>">

            <p>
                <label>Session de caisse</label><br>
                <select name="id_session" required>
                    <option value="">Sélectionner une session</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= $h($session['id_session']) ?>">
                            <?= $h($session['id_session']) ?>
                            — ouverte le <?= $h($session['date_heure_ouverture']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label>Moyen de paiement</label><br>
                <select name="moyen_paiement" required>
                    <option value="ESPECES">Espèces</option>
                    <option value="MOBILE MONEY">Mobile Money</option>
                    <option value="ORANGE MONEY">Orange Money</option>
                </select>
            </p>

            <p>
                <label>Promotion (facultatif)</label><br>
                <select name="id_promotion">
                    <option value="">Aucune promotion</option>
                    <?php foreach ($promotions as $promotion): ?>
                        <option value="<?= $h($promotion['id_promotion']) ?>">
                            <?= $h($promotion['id_promotion']) ?>
                            — <?= $h($promotion['mode_remise']) ?>
                            — <?= (int) $promotion['remise'] ?>
                            <?= $promotion['mode_remise'] === 'POURCENTAGE' ? '%' : 'FCFA' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <button type="submit">Encaisser et émettre les billets</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<h2>Billets émis</h2>

<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>Référence billet</th>
            <th>Voyageur n°</th>
            <th>Place</th>
            <th>Montant</th>
            <th>Paiement</th>
            <th>Moyen</th>
            <th>Date d'émission</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$billets): ?>
            <tr><td colspan="7">Aucun billet émis.</td></tr>
        <?php endif; ?>

        <?php foreach ($billets as $b): ?>
            <tr>
                <td><?= $h($b['id_billet']) ?></td>
                <td><?= (int) $b['numero_voyageur'] ?></td>
                <td><?= (int) $b['numero_place'] ?></td>
                <td><?= number_format((int) $b['montant_fcfa'], 0, ',', ' ') ?> FCFA</td>
                <td><?= $h($b['statut_paiement']) ?></td>
                <td><?= $h($b['moyen_paiement']) ?></td>
                <td><?= $h($b['date_emission']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
