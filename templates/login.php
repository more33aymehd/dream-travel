<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Dream Travel</title>
</head>
<body>
    <h1>Dream Travel</h1>
    <h2>Connexion</h2>

    <form action="/login" method="POST">
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        >

        <div>
            <label for="email">Adresse email</label>
            <input
                type="email"
                id="email"
                name="email"
                autocomplete="username"
                required
            >
        </div>

        <div>
            <label for="password">Mot de passe</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">Se connecter</button>
    </form>
</body>
</html>