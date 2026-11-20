
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau client - Dream Travel</title>
</head>
<body>

<h1>Ajouter un client</h1>

<p><a href="/clients">Retour à la liste</a></p>

<form action="/clients/store" method="POST">

    <input
        type="hidden"
        name="_csrf_token"
        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    >

    <div>
        <label for="nom">Nom *</label>
        <input type="text" id="nom" name="nom" maxlength="100" required>
    </div>

    <div>
        <label for="prenom">Prénom *</label>
        <input type="text" id="prenom" name="prenom" maxlength="100" required>
    </div>

    <div>
        <label for="telephone">Téléphone *</label>
        <input type="tel" id="telephone" name="telephone" maxlength="30" required>
    </div>

    <div>
        <label for="type_piece_identite">Type de pièce</label>
        <select id="type_piece_identite" name="type_piece_identite">
            <option value="">Non renseigné</option>
            <option value="CNI">CNI</option>
            <option value="PASSEPORT">Passeport</option>
            <option value="PERMIS">Permis de conduire</option>
            <option value="CARTE SEJOUR">Carte de séjour</option>
        </select>
    </div>

    <div>
        <label for="numero_piece_identite">Numéro de pièce</label>
        <input
            type="text"
            id="numero_piece_identite"
            name="numero_piece_identite"
            maxlength="50"
        >
    </div>

    <button type="submit">Enregistrer le client</button>

</form>

</body>
</html>
