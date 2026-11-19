-- =====================================================================
-- Dream Travel - Fichier 04 : comptes MySQL, roles et privileges (DCL)
-- Principe du moindre privilege (cours : GRANT / REVOKE / roles MySQL 8+).
-- A executer en tant que root, apres 01, 02 et 03.
--
-- IMPORTANT : remplacez les mots de passe 'A_CHANGER_...' avant tout usage reel
-- et gardez-les hors du depot Git (fichier .env du PHP).
-- Si PHP se connecte via 127.0.0.1 et non via 'localhost', creez aussi les
-- comptes @'127.0.0.1' (ou utilisez '%' en reseau ferme).
--
-- Deux niveaux de securite, complementaires :
--  1. ici : ce que le COMPTE de l'application peut faire dans la base ;
--  2. dans le PHP : ce que chaque AGENT peut faire (roles de la table role).
-- Les procedures et vues s'executent avec les droits de leur createur
-- (SQL SECURITY DEFINER) : le compte PHP peut donc payer ou reserver
-- uniquement par les procedures, sans avoir INSERT sur paiement ou billet.
-- =====================================================================
USE dream_travel;

-- ---------------------------------------------------------------------
-- 1. ROLES
-- ---------------------------------------------------------------------
DROP ROLE IF EXISTS role_app_base, role_app_vente, role_app_exploitation,
                    role_app_admin, role_reporting, role_auditeur;
CREATE ROLE role_app_base, role_app_vente, role_app_exploitation,
            role_app_admin, role_reporting, role_auditeur;

-- ---- role_app_base : lecture des referentiels, des vues, authentification ----
GRANT SELECT ON dream_travel.ville              TO role_app_base;
GRANT SELECT ON dream_travel.agence             TO role_app_base;
GRANT SELECT ON dream_travel.role               TO role_app_base;
GRANT SELECT ON dream_travel.employe_role       TO role_app_base;
GRANT SELECT ON dream_travel.agence_role        TO role_app_base;
GRANT SELECT ON dream_travel.itineraire         TO role_app_base;
GRANT SELECT ON dream_travel.agence_itineraire  TO role_app_base;
GRANT SELECT ON dream_travel.escale             TO role_app_base;
GRANT SELECT ON dream_travel.vehicule           TO role_app_base;
GRANT SELECT ON dream_travel.chauffeur          TO role_app_base;
GRANT SELECT ON dream_travel.voyage             TO role_app_base;
GRANT SELECT ON dream_travel.voyage_escale      TO role_app_base;
GRANT SELECT ON dream_travel.voyage_chauffeur   TO role_app_base;
GRANT SELECT ON dream_travel.tarif              TO role_app_base;
GRANT SELECT ON dream_travel.promotion          TO role_app_base;
GRANT SELECT ON dream_travel.service            TO role_app_base;
-- Connexion d'un agent : lecture du hash + mise a jour de la derniere connexion, rien d'autre.
GRANT SELECT (id_employe, id_agence, nom, prenom, email, mot_de_passe_hash, date_creation, derniere_connexion)
      ON dream_travel.employe TO role_app_base;
GRANT UPDATE (derniere_connexion) ON dream_travel.employe TO role_app_base;
-- Vues
GRANT SELECT ON dream_travel.v_places_voyage        TO role_app_base;
GRANT SELECT ON dream_travel.v_voyage_detail        TO role_app_base;
GRANT SELECT ON dream_travel.v_billet_impression    TO role_app_base;
GRANT SELECT ON dream_travel.v_recette_session      TO role_app_base;
GRANT SELECT ON dream_travel.v_activite_agence_jour TO role_app_base;
GRANT SELECT ON dream_travel.v_suivi_colis          TO role_app_base;
GRANT SELECT ON dream_travel.v_employe_roles        TO role_app_base;

-- ---- role_app_vente : billetterie, caisse, clients, colis, reclamations ----
GRANT EXECUTE ON PROCEDURE dream_travel.sp_creer_reservation    TO role_app_vente;
GRANT EXECUTE ON PROCEDURE dream_travel.sp_ajouter_voyageur     TO role_app_vente;
GRANT EXECUTE ON PROCEDURE dream_travel.sp_enregistrer_paiement TO role_app_vente;
GRANT EXECUTE ON PROCEDURE dream_travel.sp_annuler_reservation  TO role_app_vente;
GRANT EXECUTE ON PROCEDURE dream_travel.sp_ouvrir_session       TO role_app_vente;
GRANT EXECUTE ON PROCEDURE dream_travel.sp_fermer_session       TO role_app_vente;
-- Lecture seule sur les tables transactionnelles : on ne les modifie que par les procedures.
GRANT SELECT ON dream_travel.reservation           TO role_app_vente;
GRANT SELECT ON dream_travel.reservation_voyageur  TO role_app_vente;
GRANT SELECT ON dream_travel.paiement              TO role_app_vente;
GRANT SELECT ON dream_travel.billet                TO role_app_vente;
GRANT SELECT ON dream_travel.session_de_caisse     TO role_app_vente;
-- Tables sans procedure dediee (pour l'instant) : ecriture directe, jamais de DELETE.
GRANT SELECT, INSERT, UPDATE ON dream_travel.client      TO role_app_vente;
GRANT SELECT, INSERT, UPDATE ON dream_travel.colis       TO role_app_vente;
GRANT SELECT, INSERT, UPDATE ON dream_travel.reclamation TO role_app_vente;

-- ---- role_app_exploitation : planning des voyages, parc, tarifs, fournisseurs ----
GRANT INSERT, UPDATE         ON dream_travel.voyage          TO role_app_exploitation;
GRANT INSERT, UPDATE, DELETE ON dream_travel.voyage_escale   TO role_app_exploitation;
GRANT INSERT, UPDATE, DELETE ON dream_travel.voyage_chauffeur TO role_app_exploitation;
GRANT INSERT, UPDATE         ON dream_travel.vehicule        TO role_app_exploitation;
GRANT INSERT, UPDATE         ON dream_travel.chauffeur       TO role_app_exploitation;
GRANT INSERT, UPDATE         ON dream_travel.escale          TO role_app_exploitation;
GRANT INSERT, UPDATE         ON dream_travel.itineraire      TO role_app_exploitation;
GRANT INSERT, UPDATE         ON dream_travel.agence_itineraire TO role_app_exploitation;
GRANT INSERT, UPDATE         ON dream_travel.tarif           TO role_app_exploitation;
GRANT INSERT, UPDATE         ON dream_travel.promotion       TO role_app_exploitation;
GRANT SELECT, INSERT, UPDATE ON dream_travel.fournisseur     TO role_app_exploitation;
GRANT SELECT, INSERT, UPDATE ON dream_travel.livraison       TO role_app_exploitation;
GRANT EXECUTE ON PROCEDURE dream_travel.sp_definir_siege     TO role_app_exploitation;

-- ---- role_app_admin : comptes des agents, roles, agences ; lecture du journal ----
GRANT INSERT, UPDATE         ON dream_travel.agence          TO role_app_admin;
GRANT INSERT, UPDATE         ON dream_travel.ville           TO role_app_admin;
GRANT INSERT, UPDATE         ON dream_travel.role            TO role_app_admin;
GRANT INSERT, UPDATE, DELETE ON dream_travel.employe_role    TO role_app_admin;
GRANT INSERT, UPDATE, DELETE ON dream_travel.agence_role     TO role_app_admin;
GRANT INSERT, UPDATE         ON dream_travel.employe         TO role_app_admin;
GRANT SELECT                 ON dream_travel.journal_audit   TO role_app_admin;
GRANT SELECT, INSERT, UPDATE ON dream_travel.service         TO role_app_admin;

-- ---- role_reporting : tableaux de bord, lecture seule, uniquement des vues ----
GRANT SELECT ON dream_travel.v_places_voyage        TO role_reporting;
GRANT SELECT ON dream_travel.v_voyage_detail        TO role_reporting;
GRANT SELECT ON dream_travel.v_recette_session      TO role_reporting;
GRANT SELECT ON dream_travel.v_activite_agence_jour TO role_reporting;
GRANT SELECT ON dream_travel.v_suivi_colis          TO role_reporting;

-- ---- role_auditeur : lecture du journal d'audit uniquement (cf. exercice DCL 1) ----
GRANT SELECT ON dream_travel.journal_audit TO role_auditeur;

-- ---------------------------------------------------------------------
-- 2. COMPTES
-- ---------------------------------------------------------------------
DROP USER IF EXISTS 'dt_admin'@'localhost';
DROP USER IF EXISTS 'dt_app'@'localhost';
DROP USER IF EXISTS 'dt_reporting'@'localhost';
DROP USER IF EXISTS 'dt_auditeur'@'localhost';

-- Administrateur de la base (equivalent de admin_scolarite du cours)
CREATE USER 'dt_admin'@'localhost' IDENTIFIED BY 'A_CHANGER_Admin2026!';
GRANT ALL PRIVILEGES ON dream_travel.* TO 'dt_admin'@'localhost' WITH GRANT OPTION;

-- Compte utilise par l'application PHP
CREATE USER 'dt_app'@'localhost' IDENTIFIED BY 'A_CHANGER_App2026!';
GRANT role_app_base, role_app_vente, role_app_exploitation, role_app_admin TO 'dt_app'@'localhost';
SET DEFAULT ROLE ALL TO 'dt_app'@'localhost';

CREATE USER 'dt_reporting'@'localhost' IDENTIFIED BY 'A_CHANGER_Report2026!';
GRANT role_reporting TO 'dt_reporting'@'localhost';
SET DEFAULT ROLE ALL TO 'dt_reporting'@'localhost';

CREATE USER 'dt_auditeur'@'localhost' IDENTIFIED BY 'A_CHANGER_Audit2026!';
GRANT role_auditeur TO 'dt_auditeur'@'localhost';
SET DEFAULT ROLE ALL TO 'dt_auditeur'@'localhost';

-- ---------------------------------------------------------------------
-- 3. VERIFICATION (a lancer a la main)
-- ---------------------------------------------------------------------
-- SHOW GRANTS FOR 'dt_app'@'localhost' USING role_app_base, role_app_vente, role_app_exploitation, role_app_admin;
-- Revoquer un droit : REVOKE INSERT ON dream_travel.client FROM role_app_vente;
-- Supprimer un compte : DROP USER 'dt_reporting'@'localhost';
