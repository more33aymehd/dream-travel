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
USE dream_travel_test;

-- ---------------------------------------------------------------------
-- 1. ROLES
-- ---------------------------------------------------------------------
DROP ROLE IF EXISTS role_app_base_test, role_app_vente_test, role_app_exploitation_test,
                    role_app_admin_test, role_reporting_test, role_auditeur_test;
CREATE ROLE role_app_base_test, role_app_vente_test, role_app_exploitation_test,
            role_app_admin_test, role_reporting_test, role_auditeur_test;

-- ---- role_app_base_test : lecture des referentiels, des vues, authentification ----
GRANT SELECT ON dream_travel_test.ville              TO role_app_base_test;
GRANT SELECT ON dream_travel_test.agence             TO role_app_base_test;
GRANT SELECT ON dream_travel_test.role               TO role_app_base_test;
GRANT SELECT ON dream_travel_test.employe_role       TO role_app_base_test;
GRANT SELECT ON dream_travel_test.agence_role        TO role_app_base_test;
GRANT SELECT ON dream_travel_test.itineraire         TO role_app_base_test;
GRANT SELECT ON dream_travel_test.agence_itineraire  TO role_app_base_test;
GRANT SELECT ON dream_travel_test.escale             TO role_app_base_test;
GRANT SELECT ON dream_travel_test.vehicule           TO role_app_base_test;
GRANT SELECT ON dream_travel_test.chauffeur          TO role_app_base_test;
GRANT SELECT ON dream_travel_test.voyage             TO role_app_base_test;
GRANT SELECT ON dream_travel_test.voyage_escale      TO role_app_base_test;
GRANT SELECT ON dream_travel_test.voyage_chauffeur   TO role_app_base_test;
GRANT SELECT ON dream_travel_test.tarif              TO role_app_base_test;
GRANT SELECT ON dream_travel_test.promotion          TO role_app_base_test;
GRANT SELECT ON dream_travel_test.service            TO role_app_base_test;
-- Connexion d'un agent : lecture du hash + mise a jour de la derniere connexion, rien d'autre.
GRANT SELECT (id_employe, id_agence, nom, prenom, email, mot_de_passe_hash, date_creation, derniere_connexion)
      ON dream_travel_test.employe TO role_app_base_test;
GRANT UPDATE (derniere_connexion) ON dream_travel_test.employe TO role_app_base_test;
-- Vues
GRANT SELECT ON dream_travel_test.v_places_voyage        TO role_app_base_test;
GRANT SELECT ON dream_travel_test.v_voyage_detail        TO role_app_base_test;
GRANT SELECT ON dream_travel_test.v_billet_impression    TO role_app_base_test;
GRANT SELECT ON dream_travel_test.v_recette_session      TO role_app_base_test;
GRANT SELECT ON dream_travel_test.v_activite_agence_jour TO role_app_base_test;
GRANT SELECT ON dream_travel_test.v_suivi_colis          TO role_app_base_test;
GRANT SELECT ON dream_travel_test.v_employe_roles        TO role_app_base_test;

-- ---- role_app_vente_test : billetterie, caisse, clients, colis, reclamations ----
GRANT EXECUTE ON PROCEDURE dream_travel_test.sp_creer_reservation    TO role_app_vente_test;
GRANT EXECUTE ON PROCEDURE dream_travel_test.sp_ajouter_voyageur     TO role_app_vente_test;
GRANT EXECUTE ON PROCEDURE dream_travel_test.sp_enregistrer_paiement TO role_app_vente_test;
GRANT EXECUTE ON PROCEDURE dream_travel_test.sp_annuler_reservation  TO role_app_vente_test;
GRANT EXECUTE ON PROCEDURE dream_travel_test.sp_ouvrir_session       TO role_app_vente_test;
GRANT EXECUTE ON PROCEDURE dream_travel_test.sp_fermer_session       TO role_app_vente_test;
-- Lecture seule sur les tables transactionnelles : on ne les modifie que par les procedures.
GRANT SELECT ON dream_travel_test.reservation           TO role_app_vente_test;
GRANT SELECT ON dream_travel_test.reservation_voyageur  TO role_app_vente_test;
GRANT SELECT ON dream_travel_test.paiement              TO role_app_vente_test;
GRANT SELECT ON dream_travel_test.billet                TO role_app_vente_test;
GRANT SELECT ON dream_travel_test.session_de_caisse     TO role_app_vente_test;
-- Tables sans procedure dediee (pour l'instant) : ecriture directe, jamais de DELETE.
GRANT SELECT, INSERT, UPDATE ON dream_travel_test.client      TO role_app_vente_test;
GRANT SELECT, INSERT, UPDATE ON dream_travel_test.colis       TO role_app_vente_test;
GRANT SELECT, INSERT, UPDATE ON dream_travel_test.reclamation TO role_app_vente_test;

-- ---- role_app_exploitation_test : planning des voyages, parc, tarifs, fournisseurs ----
GRANT INSERT, UPDATE         ON dream_travel_test.voyage          TO role_app_exploitation_test;
GRANT INSERT, UPDATE, DELETE ON dream_travel_test.voyage_escale   TO role_app_exploitation_test;
GRANT INSERT, UPDATE, DELETE ON dream_travel_test.voyage_chauffeur TO role_app_exploitation_test;
GRANT INSERT, UPDATE         ON dream_travel_test.vehicule        TO role_app_exploitation_test;
GRANT INSERT, UPDATE         ON dream_travel_test.chauffeur       TO role_app_exploitation_test;
GRANT INSERT, UPDATE         ON dream_travel_test.escale          TO role_app_exploitation_test;
GRANT INSERT, UPDATE         ON dream_travel_test.itineraire      TO role_app_exploitation_test;
GRANT INSERT, UPDATE         ON dream_travel_test.agence_itineraire TO role_app_exploitation_test;
GRANT INSERT, UPDATE         ON dream_travel_test.tarif           TO role_app_exploitation_test;
GRANT INSERT, UPDATE         ON dream_travel_test.promotion       TO role_app_exploitation_test;
GRANT SELECT, INSERT, UPDATE ON dream_travel_test.fournisseur     TO role_app_exploitation_test;
GRANT SELECT, INSERT, UPDATE ON dream_travel_test.livraison       TO role_app_exploitation_test;
GRANT EXECUTE ON PROCEDURE dream_travel_test.sp_definir_siege     TO role_app_exploitation_test;

-- ---- role_app_admin_test : comptes des agents, roles, agences ; lecture du journal ----
GRANT INSERT, UPDATE         ON dream_travel_test.agence          TO role_app_admin_test;
GRANT INSERT, UPDATE         ON dream_travel_test.ville           TO role_app_admin_test;
GRANT INSERT, UPDATE         ON dream_travel_test.role            TO role_app_admin_test;
GRANT INSERT, UPDATE, DELETE ON dream_travel_test.employe_role    TO role_app_admin_test;
GRANT INSERT, UPDATE, DELETE ON dream_travel_test.agence_role     TO role_app_admin_test;
GRANT INSERT, UPDATE         ON dream_travel_test.employe         TO role_app_admin_test;
GRANT SELECT                 ON dream_travel_test.journal_audit   TO role_app_admin_test;
GRANT SELECT, INSERT, UPDATE ON dream_travel_test.service         TO role_app_admin_test;

-- ---- role_reporting_test : tableaux de bord, lecture seule, uniquement des vues ----
GRANT SELECT ON dream_travel_test.v_places_voyage        TO role_reporting_test;
GRANT SELECT ON dream_travel_test.v_voyage_detail        TO role_reporting_test;
GRANT SELECT ON dream_travel_test.v_recette_session      TO role_reporting_test;
GRANT SELECT ON dream_travel_test.v_activite_agence_jour TO role_reporting_test;
GRANT SELECT ON dream_travel_test.v_suivi_colis          TO role_reporting_test;

-- ---- role_auditeur_test : lecture du journal d'audit uniquement (cf. exercice DCL 1) ----
GRANT SELECT ON dream_travel_test.journal_audit TO role_auditeur_test;

-- ---------------------------------------------------------------------
-- 2. COMPTES
-- ---------------------------------------------------------------------
DROP USER IF EXISTS 'dt_admin_test'@'localhost';
DROP USER IF EXISTS 'dt_app_test'@'localhost';
DROP USER IF EXISTS 'dt_reporting_test'@'localhost';
DROP USER IF EXISTS 'dt_auditeur_test'@'localhost';

-- Administrateur de la base (equivalent de admin_scolarite du cours)
CREATE USER 'dt_admin_test'@'localhost' IDENTIFIED BY 'A_CHANGER_Admin2026!';
GRANT ALL PRIVILEGES ON dream_travel_test.* TO 'dt_admin_test'@'localhost' WITH GRANT OPTION;

-- Compte utilise par l'application PHP
CREATE USER 'dt_app_test'@'localhost' IDENTIFIED BY 'A_CHANGER_App2026!';
GRANT role_app_base_test, role_app_vente_test, role_app_exploitation_test, role_app_admin_test TO 'dt_app_test'@'localhost';
SET DEFAULT ROLE ALL TO 'dt_app_test'@'localhost';

CREATE USER 'dt_reporting_test'@'localhost' IDENTIFIED BY 'A_CHANGER_Report2026!';
GRANT role_reporting_test TO 'dt_reporting_test'@'localhost';
SET DEFAULT ROLE ALL TO 'dt_reporting_test'@'localhost';

CREATE USER 'dt_auditeur_test'@'localhost' IDENTIFIED BY 'A_CHANGER_Audit2026!';
GRANT role_auditeur_test TO 'dt_auditeur_test'@'localhost';
SET DEFAULT ROLE ALL TO 'dt_auditeur_test'@'localhost';

-- ---------------------------------------------------------------------
-- 3. VERIFICATION (a lancer a la main)
-- ---------------------------------------------------------------------
-- SHOW GRANTS FOR 'dt_app_test'@'localhost' USING role_app_base_test, role_app_vente_test, role_app_exploitation_test, role_app_admin_test;
-- Revoquer un droit : REVOKE INSERT ON dream_travel_test.client FROM role_app_vente_test;
-- Supprimer un compte : DROP USER 'dt_reporting_test'@'localhost';
