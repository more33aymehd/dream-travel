
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier client - Dream Travel</title>
</head>
<body>

<h1>Modifier un client</h1>

<p><a href="/clients">Retour à la liste</a></p>

<form action="/clients/update" method="POST">

    <input
        type="hidden"
        name="_csrf_token"
        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    >

    <input
        type="hidden"
        name="id_client"
        value="<?= htmlspecialchars($client['id_client'], ENT_QUOTES, 'UTF-8') ?>"
    >

    <div>
        <label for="nom">Nom *</label>
        <input
            type="text"
            id="nom"
            name="nom"
            maxlength="100"
            value="<?= htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8') ?>"
            required
        >
    </div>

    <div>
        <label for="prenom">Prénom *</label>
        <input
            type="text"
            id="prenom"
            name="prenom"
            maxlength="100"
            value="<?= htmlspecialchars($client['prenom'], ENT_QUOTES, 'UTF-8') ?>"
            required
        >
    </div>

    <div>
        <label for="telephone">Téléphone *</label>
        <input
            type="tel"
            id="telephone"
            name="telephone"
            maxlength="30"
            value="<?= htmlspecialchars($client['telephone'], ENT_QUOTES, 'UTF-8') ?>"
            required
        >
    </div>

    <div>
        <label for="type_piece_identite">Type de pièce</label>
        <select id="type_piece_identite" name="type_piece_identite">
            <option value="">Non renseigné</option>

            <option value="CNI" <?= $client['type_piece_identite'] === 'CNI' ? 'selected' : '' ?>>
                CNI
            </option>

            <option value="PASSEPORT" <?= $client['type_piece_identite'] === 'PASSEPORT' ? 'selected' : '' ?>>
                Passeport
            </option>

            <option value="PERMIS" <?= $client['type_piece_identite'] === 'PERMIS' ? 'selected' : '' ?>>
                Permis de conduire
            </option>

            <option value="CARTE SEJOUR" <?= $client['type_piece_identite'] === 'CARTE SEJOUR' ? 'selected' : '' ?>>
                Carte de séjour
            </option>
        </select>
    </div>

    <div>
        <label for="numero_piece_identite">Numéro de pièce</label>
        <input
            type="text"
            id="numero_piece_identite"
            name="numero_piece_identite"
            maxlength="50"
            value="<?= htmlspecialchars($client['numero_piece_identite'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <button type="submit">Enregistrer les modifications</button>

</form>

</body>
</html>
