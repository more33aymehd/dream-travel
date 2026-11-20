
<?php
$h = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<h1>Gestion des réservations</h1>

<p>
    <a href="/">Tableau de bord</a>
    |
    <?php if (Access::hasPermission('reservation.create')): ?>
        <a href="/reservations/create">Nouvelle réservation</a>
    <?php endif; ?>
</p>

<form method="GET" action="/reservations">
    <input
        type="search"
        name="q"
        placeholder="Référence, nom, prénom ou téléphone"
        value="<?= $h($search) ?>"
    >
    <button type="submit">Rechercher</button>
    <a href="/reservations">Réinitialiser</a>
</form>

<?php if (isset($_GET['status']) && $_GET['status'] === 'created'): ?>
    <p>Réservation créée.</p>
<?php endif; ?>

<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>Référence</th>
            <th>Client</th>
            <th>Itinéraire</th>
            <th>Départ</th>
            <th>Expiration</th>
            <th>Montant</th>
            <th>Statut</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$reservations): ?>
            <tr>
                <td colspan="8">Aucune réservation trouvée.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($reservations as $r): ?>
            <tr>
                <td><?= $h($r['id_reservation']) ?></td>
                <td><?= $h($r['client_prenom']) ?>
                    <?= $h($r['client_nom']) ?>
                </td>
                <td><?= $h($r['itineraire']) ?></td>
                <td>
                    <?= $h($r['date_voyage']) ?>
                    à <?= $h($r['heure_depart_prevue']) ?>
                </td>
                <td><?= $h($r['date_expiration']) ?></td>
                <td><?= number_format((int) $r['montant_total_fcfa'], 0, ',', ' ') ?> FCFA</td>
                <td><?= $h($r['statut']) ?></td>
                <td>
                    <a href="/reservations/show?id=<?= rawurlencode($r['id_reservation']) ?>">
                        Consulter
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
