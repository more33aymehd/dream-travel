# Dream Travel (Blue Bird Express)

Application web de gestion d'une agence de transport : réservations, billets, paiements, caisse, colis, réclamations.
Stack : PHP (PDO), HTML, CSS, JavaScript, MySQL 8+. Méthode de conception : MERISE (voir le rapport Blue Bird V2).

## Structure du projet

```
dreamtravel/
├── README.md
├── database/                 (fait)
│   ├── 01_schema.sql         tables, clés étrangères, contraintes CI
│   ├── 02_donnees.sql        données de test corrigées
│   ├── 03_logique.sql        procédures, triggers, vues, événement
│   └── 04_securite.sql       comptes et rôles MySQL
├── config/
│   ├── config.php            lit le fichier .env
│   └── .env.example          modèle (le vrai .env n'est jamais versionné)
├── src/
│   ├── Database.php          connexion PDO (compte dt_app)
│   ├── Auth.php              connexion / déconnexion des agents, rôles
│   └── helpers.php           échappement HTML, messages, redirections
├── templates/
│   ├── header.php
│   └── footer.php
└── public/                   seul dossier exposé par le serveur web
    ├── index.php             tableau de bord
    ├── login.php / logout.php
    ├── voyages.php           liste des voyages et places libres
    ├── reservation_nouvelle.php
    ├── paiement.php
    ├── billet.php            billet imprimable
    ├── caisse.php            ouverture / fermeture de session
    └── assets/
        ├── css/style.css
        └── js/app.js
```

## Étapes de création

1. **Base de données** (fait)
   - Importer dans l'ordre : `01_schema.sql`, `02_donnees.sql`, `03_logique.sql`, `04_securite.sql`.
   - Changer les mots de passe `A_CHANGER_...` dans `04_securite.sql` avant tout usage réel.
2. **Socle PHP**
   - Créer les dossiers, le fichier `.env` (à partir de `.env.example`), `config.php`, `Database.php`.
   - Vérifier la connexion avec une page de test.
3. **Authentification**
   - `login.php` : `password_verify()` sur `employe.mot_de_passe_hash`.
   - Après connexion : mettre à jour `derniere_connexion` et exécuter `SET @app_employe_id = '...'` à chaque requête (nécessaire au journal d'audit).
   - Remplacer les hash factices de `02_donnees.sql` par de vrais `password_hash()`.
4. **Droits des agents (côté PHP)**
   - Lire les rôles via `v_employe_roles`.
   - Bloquer chaque page selon le rôle : Réceptionniste, Comptable, Chef d'agence, Administrateur.
5. **Vente de billets**
   - `voyages.php` (vue `v_places_voyage`) puis `reservation_nouvelle.php` (`sp_creer_reservation`, `sp_ajouter_voyageur`).
   - `paiement.php` (`sp_enregistrer_paiement`) puis `billet.php` (vue `v_billet_impression`).
6. **Caisse**
   - `caisse.php` : `sp_ouvrir_session`, `sp_fermer_session`, vue `v_recette_session`.
7. **Colis et réclamations**
   - Formulaires sur les tables `colis` et `reclamation`, suivi avec `v_suivi_colis`.
8. **Exploitation et administration**
   - Planning des voyages, véhicules, chauffeurs, tarifs, promotions, comptes des agents.
9. **Tableau de bord du chef d'agence**
   - Vue `v_activite_agence_jour`, liste des sessions de caisse, consultation du journal d'audit.
10. **Finitions**
    - Protection CSRF, requêtes préparées partout, messages d'erreur lisibles (les erreurs des procédures arrivent en SQLSTATE 45000), tests de bout en bout, sauvegarde de la base.

## Règles à respecter

- Toute écriture sur `reservation`, `paiement`, `billet` et `session_de_caisse` passe par les procédures stockées.
- Le compte `dt_app` n'a aucun droit de suppression sur les données métier.
- Le dossier `public/` est le seul accessible depuis le navigateur.
