-- =====================================================================
-- Dream Travel (Blue Bird Express) - MPD V2 corrige
-- Fichier 01 : schema (InnoDB, cles etrangeres, contraintes CI)
-- Base de depart : votre dump V1 (cles varchar, noms de colonnes conserves)
-- Reference      : rapport Blue Bird V2 (RG01-RG72, CI01-CI22)
-- Cible          : MySQL 8.0.16+ / 9.x
-- ATTENTION      : supprime les tables existantes de la base dream_travel.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS dream_travel
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dream_travel;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS journal_audit, livraison, fournisseur, reclamation, colis, service,
  billet, paiement_promotion, paiement, promotion, session_de_caisse,
  reservation_voyageur, reservation, client, tarif,
  voyage_escale, voyage_chauffeur, voyage, chauffeur, escale, vehicule,
  agence_itineraire, itineraire, agence_role, employe_role, employe, role, agence, ville, entreprise;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. ORGANISATION ET ADMINISTRATION
-- ---------------------------------------------------------------------

CREATE TABLE entreprise (
  id_entreprise   VARCHAR(20)  NOT NULL,
  nom_commercial  VARCHAR(100) NOT NULL,
  telephone       VARCHAR(30),
  email           VARCHAR(100),
  logo            VARCHAR(255),
  PRIMARY KEY (id_entreprise),
  UNIQUE KEY uq_entreprise_nom (nom_commercial)
) ENGINE=InnoDB;

CREATE TABLE ville (
  id_ville   VARCHAR(20)  NOT NULL,
  nom_ville  VARCHAR(100) NOT NULL,
  region     VARCHAR(100) NOT NULL,
  pays       VARCHAR(100) NOT NULL DEFAULT 'Cameroun',
  PRIMARY KEY (id_ville),
  UNIQUE KEY uq_ville (nom_ville, region, pays)
) ENGINE=InnoDB;

CREATE TABLE agence (
  id_agence      VARCHAR(20)  NOT NULL,
  id_entreprise  VARCHAR(20)  NOT NULL,               -- RG03
  id_ville       VARCHAR(20)  NOT NULL,               -- RG05
  nom_agence     VARCHAR(150) NOT NULL,
  adresse        VARCHAR(150),
  localisation   VARCHAR(100),
  est_siege      TINYINT(1)   NOT NULL DEFAULT 0,     -- V1 : 'Oui'/'Non'
  -- CI02 : au plus un siege par entreprise (NULL si ce n'est pas le siege).
  siege_unique   VARCHAR(20) AS (IF(est_siege = 1, id_entreprise, NULL)) VIRTUAL,
  PRIMARY KEY (id_agence),
  UNIQUE KEY uq_agence_nom (id_entreprise, nom_agence),
  UNIQUE KEY uq_agence_siege (siege_unique),
  KEY fk_agence_ville (id_ville),
  CONSTRAINT fk_agence_entreprise FOREIGN KEY (id_entreprise) REFERENCES entreprise (id_entreprise),
  CONSTRAINT fk_agence_ville      FOREIGN KEY (id_ville)      REFERENCES ville (id_ville),
  CONSTRAINT ck_agence_siege CHECK (est_siege IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE role (
  id_role      VARCHAR(20)  NOT NULL,
  libelle      VARCHAR(100) NOT NULL,
  description  TEXT,
  portee       ENUM('PLATEFORME','ENTREPRISE','AGENCE') NOT NULL DEFAULT 'AGENCE',
  PRIMARY KEY (id_role),
  UNIQUE KEY uq_role_libelle (libelle)
) ENGINE=InnoDB;

CREATE TABLE employe (
  id_employe          VARCHAR(20)  NOT NULL,
  id_agence           VARCHAR(20)  NOT NULL,          -- RG13
  nom                 VARCHAR(100) NOT NULL,
  prenom              VARCHAR(100) NOT NULL,
  email               VARCHAR(100) NOT NULL,
  mot_de_passe_hash   VARCHAR(255) NOT NULL,          -- password_hash() PHP
  date_creation       DATE NOT NULL,
  derniere_connexion  DATETIME,
  PRIMARY KEY (id_employe),
  UNIQUE KEY uq_employe_email (email),
  KEY fk_employe_agence (id_agence),
  CONSTRAINT fk_employe_agence FOREIGN KEY (id_agence) REFERENCES agence (id_agence)
) ENGINE=InnoDB;

-- RG14 / RG15
CREATE TABLE employe_role (
  id_employe        VARCHAR(20) NOT NULL,
  id_role           VARCHAR(20) NOT NULL,
  date_attribution  DATE,
  PRIMARY KEY (id_employe, id_role),
  KEY fk_er_role (id_role),
  CONSTRAINT fk_er_employe FOREIGN KEY (id_employe) REFERENCES employe (id_employe),
  CONSTRAINT fk_er_role    FOREIGN KEY (id_role)    REFERENCES role (id_role)
) ENGINE=InnoDB;

-- RG16 : association AGENCE-ROLE portant le telephone professionnel
CREATE TABLE agence_role (
  id_agence                VARCHAR(20) NOT NULL,
  id_role                  VARCHAR(20) NOT NULL,
  telephone_professionnel  VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_agence, id_role),
  KEY fk_ar_role (id_role),
  CONSTRAINT fk_ar_agence FOREIGN KEY (id_agence) REFERENCES agence (id_agence),
  CONSTRAINT fk_ar_role   FOREIGN KEY (id_role)   REFERENCES role (id_role)
) ENGINE=InnoDB;

-- RG70-RG72 : chaque entree est rattachee a un employe ET a une agence
CREATE TABLE journal_audit (
  id_audit                VARCHAR(20) NOT NULL,
  id_employe              VARCHAR(20) NOT NULL,
  id_agence               VARCHAR(20) NOT NULL,
  type_objet              VARCHAR(50) NOT NULL,
  identifiant_objet       VARCHAR(20) NOT NULL,       -- polymorphe : pas de cle etrangere
  action                  VARCHAR(50) NOT NULL,
  date_heure_action       DATETIME NOT NULL,
  resultat                ENUM('SUCCES','ECHEC') NOT NULL DEFAULT 'SUCCES',
  description_changement  TEXT,
  PRIMARY KEY (id_audit),
  KEY ix_audit_objet (type_objet, identifiant_objet),
  KEY fk_audit_employe (id_employe),
  KEY fk_audit_agence (id_agence),
  CONSTRAINT fk_audit_employe FOREIGN KEY (id_employe) REFERENCES employe (id_employe),
  CONSTRAINT fk_audit_agence  FOREIGN KEY (id_agence)  REFERENCES agence (id_agence)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. TRANSPORT ET EXPLOITATION
-- ---------------------------------------------------------------------

-- RG23 : le depart et l'arrivee d'un itineraire se deduisent des escales.
CREATE TABLE itineraire (
  id_itineraire       VARCHAR(20)  NOT NULL,
  libelle             VARCHAR(100) NOT NULL,
  distance_totale_km  INT NOT NULL,
  duree_estimee_min   INT NOT NULL,
  trace_geographique  VARCHAR(100),
  statut              ENUM('ACTIF','INACTIF') NOT NULL DEFAULT 'ACTIF',
  PRIMARY KEY (id_itineraire),
  CONSTRAINT ck_it_valeurs CHECK (distance_totale_km >= 0 AND duree_estimee_min >= 0)   -- CI10
) ENGINE=InnoDB;

-- RG07 / RG08
CREATE TABLE agence_itineraire (
  id_agence                VARCHAR(20) NOT NULL,
  id_itineraire            VARCHAR(20) NOT NULL,
  date_debut_exploitation  DATE,
  PRIMARY KEY (id_agence, id_itineraire),
  KEY fk_ai_itineraire (id_itineraire),
  CONSTRAINT fk_ai_agence     FOREIGN KEY (id_agence)     REFERENCES agence (id_agence),
  CONSTRAINT fk_ai_itineraire FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire)
) ENGINE=InnoDB;

CREATE TABLE vehicule (
  immatriculation    VARCHAR(20) NOT NULL,            -- CI01
  id_entreprise      VARCHAR(20) NOT NULL,            -- RG27
  marque             VARCHAR(50) NOT NULL,
  modele             VARCHAR(50) NOT NULL,
  capacite_totale    INT NOT NULL,
  classe_voyage      ENUM('STANDARD','VIP') NOT NULL DEFAULT 'STANDARD',   -- RG28
  etat_operationnel  ENUM('OPERATIONNEL','EN MAINTENANCE','HORS SERVICE') NOT NULL DEFAULT 'OPERATIONNEL',
  PRIMARY KEY (immatriculation),
  KEY fk_vehicule_entreprise (id_entreprise),
  CONSTRAINT fk_vehicule_entreprise FOREIGN KEY (id_entreprise) REFERENCES entreprise (id_entreprise),
  CONSTRAINT ck_veh_capacite CHECK (capacite_totale > 0)     -- CI09
) ENGINE=InnoDB;

-- Escale = lieu d'arret (une ville) ; son rang dans un voyage est dans voyage_escale.
CREATE TABLE escale (
  id_escale              VARCHAR(20)  NOT NULL,
  id_ville               VARCHAR(20)  NOT NULL,
  nom_escale             VARCHAR(100) NOT NULL,
  autorise_embarquement  TINYINT(1) NOT NULL DEFAULT 1,
  autorise_debarquement  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_escale),
  KEY fk_escale_ville (id_ville),
  CONSTRAINT fk_escale_ville FOREIGN KEY (id_ville) REFERENCES ville (id_ville),
  CONSTRAINT ck_esc_flags CHECK (autorise_embarquement IN (0,1) AND autorise_debarquement IN (0,1))
) ENGINE=InnoDB;

-- RG25 : un chauffeur est une fonction exercee par un employe (id_employe).
-- nom/prenom/telephone sont gardes car le rapport les liste dans CHAUFFEUR.
CREATE TABLE chauffeur (
  id_chauffeur      VARCHAR(20)  NOT NULL,
  id_employe        VARCHAR(20)  NOT NULL,
  nom               VARCHAR(100) NOT NULL,
  prenom            VARCHAR(100) NOT NULL,
  telephone         VARCHAR(30)  NOT NULL,
  numero_permis     VARCHAR(50)  NOT NULL,
  categorie_permis  ENUM('B','C','D','E') NOT NULL DEFAULT 'D',
  statut            ENUM('DISPONIBLE','EN VOYAGE','INDISPONIBLE') NOT NULL DEFAULT 'DISPONIBLE',
  PRIMARY KEY (id_chauffeur),
  UNIQUE KEY uq_chauffeur_employe (id_employe),
  UNIQUE KEY uq_chauffeur_permis (numero_permis),
  CONSTRAINT fk_chauffeur_employe FOREIGN KEY (id_employe) REFERENCES employe (id_employe),
  CONSTRAINT ck_chf_permis CHECK (numero_permis REGEXP '^[A-Za-z0-9/-]{6,50}$')     -- CI18
) ENGINE=InnoDB;

-- Date et heures : types TIME comme en V1 (pas de voyage de nuit sur l'axe Yaounde-Ouest).
CREATE TABLE voyage (
  id_voyage             VARCHAR(20) NOT NULL,
  id_itineraire         VARCHAR(20) NOT NULL,          -- RG20
  immatriculation       VARCHAR(20) NOT NULL,          -- RG26
  date_voyage           DATE NOT NULL,
  heure_depart_prevue   TIME NOT NULL,
  heure_arrivee_prevue  TIME NOT NULL,
  heure_depart_reelle   TIME,
  heure_arrivee_reelle  TIME,
  statut                ENUM('PROGRAMME','EN COURS','TERMINE','ANNULE') NOT NULL DEFAULT 'PROGRAMME',
  PRIMARY KEY (id_voyage),
  KEY ix_voyage_date (date_voyage, statut),
  KEY fk_voyage_itineraire (id_itineraire),
  KEY fk_voyage_vehicule (immatriculation, date_voyage),
  CONSTRAINT fk_voyage_itineraire FOREIGN KEY (id_itineraire)   REFERENCES itineraire (id_itineraire),
  CONSTRAINT fk_voyage_vehicule   FOREIGN KEY (immatriculation) REFERENCES vehicule (immatriculation),
  CONSTRAINT ck_voy_prevu CHECK (heure_arrivee_prevue > heure_depart_prevue),
  CONSTRAINT ck_voy_reel  CHECK (heure_arrivee_reelle IS NULL OR heure_depart_reelle IS NULL
                                 OR heure_arrivee_reelle >= heure_depart_reelle)
) ENGINE=InnoDB;

-- RG24 : un voyage mobilise 1..N chauffeurs. Table associative (au lieu de
-- voyage.id_chauffeur en V1) pour permettre plusieurs chauffeurs par voyage.
CREATE TABLE voyage_chauffeur (
  id_voyage     VARCHAR(20) NOT NULL,
  id_chauffeur  VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_voyage, id_chauffeur),
  KEY fk_vc_chauffeur (id_chauffeur),
  CONSTRAINT fk_vc_voyage    FOREIGN KEY (id_voyage)    REFERENCES voyage (id_voyage),
  CONSTRAINT fk_vc_chauffeur FOREIGN KEY (id_chauffeur) REFERENCES chauffeur (id_chauffeur)
) ENGINE=InnoDB;

-- RG21-RG23 + CI22 : escales d'un voyage, ordonnees, sans doublon de rang.
CREATE TABLE voyage_escale (
  id_voyage             VARCHAR(20) NOT NULL,
  id_escale             VARCHAR(20) NOT NULL,
  ordre_passage         INT NOT NULL,
  heure_passage_prevue  TIME,
  PRIMARY KEY (id_voyage, id_escale),
  UNIQUE KEY uq_ve_ordre (id_voyage, ordre_passage),      -- CI22
  KEY fk_ve_escale (id_escale),
  CONSTRAINT fk_ve_voyage FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage),
  CONSTRAINT fk_ve_escale FOREIGN KEY (id_escale) REFERENCES escale (id_escale),
  CONSTRAINT ck_ve_ordre CHECK (ordre_passage >= 1)
) ENGINE=InnoDB;

-- RG11 : tarifs fixes par l'entreprise (categorie = classe de voyage ou ETUDIANT)
CREATE TABLE tarif (
  id_tarif             VARCHAR(20) NOT NULL,
  id_entreprise        VARCHAR(20) NOT NULL,
  categorie_tarif      ENUM('STANDARD','VIP','ETUDIANT') NOT NULL,
  montant_fcfa         INT NOT NULL,
  date_debut_validite  DATE NOT NULL,
  date_fin_validite    DATE NOT NULL,
  statut               ENUM('ACTIF','INACTIF') NOT NULL DEFAULT 'ACTIF',
  PRIMARY KEY (id_tarif),
  KEY fk_tarif_entreprise (id_entreprise),
  CONSTRAINT fk_tarif_entreprise FOREIGN KEY (id_entreprise) REFERENCES entreprise (id_entreprise),
  CONSTRAINT ck_tar_montant CHECK (montant_fcfa >= 0),                      -- CI06
  CONSTRAINT ck_tar_dates   CHECK (date_fin_validite >= date_debut_validite) -- CI11
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. CLIENTS, RESERVATIONS, BILLETS, PAIEMENTS, CAISSE
-- ---------------------------------------------------------------------

CREATE TABLE client (
  id_client              VARCHAR(20)  NOT NULL,
  nom                    VARCHAR(100) NOT NULL,
  prenom                 VARCHAR(100) NOT NULL,
  telephone              VARCHAR(30)  NOT NULL,
  type_piece_identite    ENUM('CNI','PASSEPORT','PERMIS','CARTE SEJOUR'),
  numero_piece_identite  VARCHAR(50),
  date_creation          DATE NOT NULL,
  PRIMARY KEY (id_client),
  UNIQUE KEY uq_client_piece (type_piece_identite, numero_piece_identite),
  KEY ix_client_tel (telephone),
  CONSTRAINT ck_client_piece CHECK ((type_piece_identite IS NULL) = (numero_piece_identite IS NULL))   -- CI19
) ENGINE=InnoDB;

-- RG31-RG34 (+ id_employe : agent qui a saisi la reservation, ajout V1 conserve)
CREATE TABLE reservation (
  id_reservation          VARCHAR(20) NOT NULL,
  id_client               VARCHAR(20) NOT NULL,        -- RG32
  id_voyage               VARCHAR(20) NOT NULL,        -- RG33
  id_employe              VARCHAR(20) NOT NULL,
  date_heure_reservation  DATETIME NOT NULL,
  date_expiration         DATETIME NOT NULL,
  montant_total_fcfa      INT NOT NULL DEFAULT 0,      -- montant brut, avant promotion
  statut                  ENUM('EN ATTENTE','CONFIRMEE','EXPIREE','ANNULEE') NOT NULL DEFAULT 'EN ATTENTE',
  PRIMARY KEY (id_reservation),
  UNIQUE KEY uq_resa_voyage (id_reservation, id_voyage),   -- cible de la FK composite de reservation_voyageur
  KEY fk_reservation_client (id_client),
  KEY fk_reservation_voyage (id_voyage),
  KEY fk_reservation_employe (id_employe),
  KEY ix_resa_statut (statut, date_expiration),
  CONSTRAINT fk_reservation_client  FOREIGN KEY (id_client)  REFERENCES client (id_client),
  CONSTRAINT fk_reservation_voyage  FOREIGN KEY (id_voyage)  REFERENCES voyage (id_voyage),
  CONSTRAINT fk_reservation_employe FOREIGN KEY (id_employe) REFERENCES employe (id_employe),
  CONSTRAINT ck_resa_expiration CHECK (date_expiration > date_heure_reservation),   -- CI13
  CONSTRAINT ck_resa_montant    CHECK (montant_total_fcfa >= 0)
) ENGINE=InnoDB;

-- RG35 : identite de chaque voyageur connue des la creation de la reservation.
-- Porte aussi la place retenue : (id_voyage, place_active) unique = jamais
-- deux reservations actives sur le meme siege (objectif "zero double reservation").
CREATE TABLE reservation_voyageur (
  id_reservation   VARCHAR(20) NOT NULL,
  numero_voyageur  INT NOT NULL,
  id_voyage        VARCHAR(20) NOT NULL,               -- recopie de reservation.id_voyage
  nom_voyageur     VARCHAR(100) NOT NULL,
  prenom_voyageur  VARCHAR(100) NOT NULL,
  type_piece       ENUM('CNI','PASSEPORT','PERMIS','CARTE SEJOUR'),
  numero_piece     VARCHAR(50),
  numero_place     INT,                                -- CI17
  montant_fcfa     INT NOT NULL DEFAULT 0,             -- prix de cette place (tarif applique)
  actif            TINYINT(1) NOT NULL DEFAULT 1,      -- 0 si reservation expiree / annulee
  place_active     INT AS (IF(actif = 1, numero_place, NULL)) VIRTUAL,
  PRIMARY KEY (id_reservation, numero_voyageur),
  UNIQUE KEY uq_place_voyage (id_voyage, place_active),
  CONSTRAINT fk_rv_reservation FOREIGN KEY (id_reservation, id_voyage)
                               REFERENCES reservation (id_reservation, id_voyage),
  CONSTRAINT ck_rv_numero CHECK (numero_voyageur >= 1),
  CONSTRAINT ck_rv_place  CHECK (numero_place IS NULL OR numero_place >= 1),
  CONSTRAINT ck_rv_montant CHECK (montant_fcfa >= 0),
  CONSTRAINT ck_rv_piece  CHECK ((type_piece IS NULL) = (numero_piece IS NULL))
) ENGINE=InnoDB;

CREATE TABLE session_de_caisse (
  id_session            VARCHAR(20) NOT NULL,
  id_agence             VARCHAR(20) NOT NULL,          -- RG64
  id_employe            VARCHAR(20) NOT NULL,          -- RG66
  date_heure_ouverture  DATETIME NOT NULL,
  date_heure_fermeture  DATETIME,
  total_encaisse_fcfa   INT NOT NULL DEFAULT 0,        -- RG68 : pas de fonds initial ; RG69/CI03 : somme des paiements
  statut_session        ENUM('OUVERTE','FERMEE') NOT NULL DEFAULT 'OUVERTE',
  -- un employe ne peut avoir qu'une seule session ouverte
  employe_ouvert        VARCHAR(20) AS (IF(statut_session = 'OUVERTE', id_employe, NULL)) VIRTUAL,
  PRIMARY KEY (id_session),
  UNIQUE KEY uq_session_ouverte (employe_ouvert),
  KEY fk_session_agence (id_agence),
  KEY fk_session_employe (id_employe),
  CONSTRAINT fk_session_agence  FOREIGN KEY (id_agence)  REFERENCES agence (id_agence),
  CONSTRAINT fk_session_employe FOREIGN KEY (id_employe) REFERENCES employe (id_employe),
  CONSTRAINT ck_sc_fermeture CHECK (date_heure_fermeture IS NULL
                                    OR date_heure_fermeture > date_heure_ouverture),   -- CI14
  CONSTRAINT ck_sc_statut CHECK ((statut_session = 'FERMEE') = (date_heure_fermeture IS NOT NULL)),
  CONSTRAINT ck_sc_total  CHECK (total_encaisse_fcfa >= 0)
) ENGINE=InnoDB;

CREATE TABLE promotion (
  id_promotion  VARCHAR(20)  NOT NULL,
  libelle       VARCHAR(100) NOT NULL,
  mode_remise   ENUM('POURCENTAGE','MONTANT') NOT NULL,
  remise        INT NOT NULL,
  date_debut    DATE NOT NULL,
  date_fin      DATE NOT NULL,
  public_cible  VARCHAR(100),
  PRIMARY KEY (id_promotion),
  CONSTRAINT ck_promo_dates  CHECK (date_fin >= date_debut),                 -- CI12
  CONSTRAINT ck_promo_remise CHECK ((mode_remise = 'POURCENTAGE' AND remise BETWEEN 1 AND 100)
                                    OR (mode_remise = 'MONTANT' AND remise > 0))   -- CI20
) ENGINE=InnoDB;

-- RG39-RG45 : paiement enregistre apres encaissement.
-- Une promotion au plus par paiement (RG45) : id_promotion + montant de la remise
-- (remplace la table paiement_promotion de la V1).
CREATE TABLE paiement (
  id_paiement          VARCHAR(20) NOT NULL,
  id_reservation       VARCHAR(20),                    -- NULL autorise (RG41)
  id_session           VARCHAR(20) NOT NULL,           -- RG69 (l'employe est celui de la session)
  id_promotion         VARCHAR(20),
  montant_remise_fcfa  INT,
  montant_fcfa         INT NOT NULL,                   -- montant reellement encaisse (apres remise)
  moyen_paiement       ENUM('ESPECES','MOBILE MONEY','ORANGE MONEY') NOT NULL,
  date_heure_paiement  DATETIME NOT NULL,
  statut_paiement      ENUM('ENCAISSE','ANNULE','REMBOURSE') NOT NULL DEFAULT 'ENCAISSE',
  PRIMARY KEY (id_paiement),
  UNIQUE KEY uq_paiement_resa (id_reservation),        -- (0,1) cote reservation ; NULL multiples permis
  KEY fk_paiement_session (id_session),
  KEY fk_paiement_promotion (id_promotion),
  CONSTRAINT fk_paiement_reservation FOREIGN KEY (id_reservation) REFERENCES reservation (id_reservation),
  CONSTRAINT fk_paiement_session     FOREIGN KEY (id_session)     REFERENCES session_de_caisse (id_session),
  CONSTRAINT fk_paiement_promotion   FOREIGN KEY (id_promotion)   REFERENCES promotion (id_promotion),
  CONSTRAINT ck_pay_montant CHECK (montant_fcfa > 0),                              -- CI08
  CONSTRAINT ck_pay_remise  CHECK ((id_promotion IS NULL) = (montant_remise_fcfa IS NULL)
                                   AND (montant_remise_fcfa IS NULL OR montant_remise_fcfa >= 0))
) ENGINE=InnoDB;

-- RG36-RG38 : un billet = un voyageur d'une reservation + un paiement (obligatoire).
-- Une reservation de groupe donne donc plusieurs billets pour un seul paiement.
CREATE TABLE billet (
  id_billet        VARCHAR(20) NOT NULL,
  id_reservation   VARCHAR(20) NOT NULL,
  numero_voyageur  INT NOT NULL,
  id_paiement      VARCHAR(20) NOT NULL,               -- RG38
  numero_place     INT,
  montant_fcfa     INT NOT NULL,
  date_emission    DATETIME NOT NULL,
  PRIMARY KEY (id_billet),
  UNIQUE KEY uq_billet_voyageur (id_reservation, numero_voyageur),
  KEY fk_billet_paiement (id_paiement),
  CONSTRAINT fk_billet_voyageur FOREIGN KEY (id_reservation, numero_voyageur)
                                REFERENCES reservation_voyageur (id_reservation, numero_voyageur),
  CONSTRAINT fk_billet_paiement FOREIGN KEY (id_paiement) REFERENCES paiement (id_paiement),
  CONSTRAINT ck_bil_montant CHECK (montant_fcfa >= 0),                              -- CI07
  CONSTRAINT ck_bil_place   CHECK (numero_place IS NULL OR numero_place >= 1)       -- CI17
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. SERVICES, COLIS, RECLAMATIONS
-- ---------------------------------------------------------------------

CREATE TABLE service (
  id_service       VARCHAR(20)  NOT NULL,
  id_entreprise    VARCHAR(20)  NOT NULL,              -- RG10
  libelle_service  VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_service),
  UNIQUE KEY uq_service (id_entreprise, libelle_service),
  CONSTRAINT fk_service_entreprise FOREIGN KEY (id_entreprise) REFERENCES entreprise (id_entreprise)
) ENGINE=InnoDB;

CREATE TABLE colis (
  id_colis                VARCHAR(20) NOT NULL,
  id_client_expediteur    VARCHAR(20) NOT NULL,        -- RG48
  nom_destinataire        VARCHAR(100) NOT NULL,
  telephone_destinataire  VARCHAR(30)  NOT NULL,
  description_contenu     TEXT NOT NULL,
  valeur_declaree_fcfa    INT NOT NULL DEFAULT 0,
  tarif_suggere_fcfa      INT NOT NULL,                -- RG50 / CI04
  montant_confirme_fcfa   INT,                         -- RG51
  date_heure_depot        DATETIME NOT NULL,           -- RG52
  date_heure_arrivee      DATETIME,                    -- RG53
  date_heure_retrait      DATETIME,                    -- RG54
  etat_materiel_remise    VARCHAR(50),
  statut_colis            ENUM('DEPOSE','EN TRANSIT','ARRIVE','RETIRE','PERDU') NOT NULL DEFAULT 'DEPOSE',
  PRIMARY KEY (id_colis),
  KEY fk_colis_client (id_client_expediteur),
  KEY ix_colis_statut (statut_colis),
  CONSTRAINT fk_colis_client FOREIGN KEY (id_client_expediteur) REFERENCES client (id_client),
  CONSTRAINT ck_col_montants CHECK (valeur_declaree_fcfa >= 0 AND tarif_suggere_fcfa >= 0
                                    AND (montant_confirme_fcfa IS NULL OR montant_confirme_fcfa >= 0)),
  CONSTRAINT ck_col_chrono CHECK (                      -- CI15 : depot <= arrivee <= retrait
        (date_heure_arrivee IS NULL OR date_heure_arrivee >= date_heure_depot)
    AND (date_heure_retrait IS NULL
         OR (date_heure_arrivee IS NOT NULL AND date_heure_retrait >= date_heure_arrivee)))
) ENGINE=InnoDB;

-- RG59 : l'indemnisation est traitee hors application ; date_heure_traitement
-- (ajout V1) sert seulement a dater la cloture du dossier.
CREATE TABLE reclamation (
  id_reclamation             VARCHAR(20) NOT NULL,
  id_client                  VARCHAR(20) NOT NULL,     -- RG56
  id_colis                   VARCHAR(20),              -- RG57 : au maximum un colis
  id_service                 VARCHAR(20),              -- RG58 : service destinataire
  date_heure_enregistrement  DATETIME NOT NULL,
  motif                      VARCHAR(100) NOT NULL,
  description                TEXT,
  date_heure_transmission    DATETIME,
  statut_transmission        ENUM('EN ATTENTE','TRANSMISE','CLOTUREE') NOT NULL DEFAULT 'EN ATTENTE',
  date_heure_traitement      DATETIME,
  PRIMARY KEY (id_reclamation),
  KEY fk_reclamation_client (id_client),
  KEY fk_reclamation_colis (id_colis),
  KEY fk_reclamation_service (id_service),
  CONSTRAINT fk_reclamation_client  FOREIGN KEY (id_client)  REFERENCES client (id_client),
  CONSTRAINT fk_reclamation_colis   FOREIGN KEY (id_colis)   REFERENCES colis (id_colis),
  CONSTRAINT fk_reclamation_service FOREIGN KEY (id_service) REFERENCES service (id_service),
  CONSTRAINT ck_rec_transmission CHECK (date_heure_transmission IS NULL
                                        OR date_heure_transmission >= date_heure_enregistrement),   -- CI16
  CONSTRAINT ck_rec_traitement CHECK (date_heure_traitement IS NULL
                                      OR date_heure_traitement >= date_heure_enregistrement)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. FOURNISSEURS ET LIVRAISONS
-- ---------------------------------------------------------------------

CREATE TABLE fournisseur (
  id_fournisseur  VARCHAR(20)  NOT NULL,
  raison_sociale  VARCHAR(150) NOT NULL,
  contact         VARCHAR(50),
  PRIMARY KEY (id_fournisseur),
  UNIQUE KEY uq_fournisseur (raison_sociale)
) ENGINE=InnoDB;

CREATE TABLE livraison (
  num_bordereau   VARCHAR(20) NOT NULL,                -- RG62 / CI01
  id_fournisseur  VARCHAR(20) NOT NULL,                -- RG61
  date_livraison  DATE NOT NULL,
  designation     TEXT,
  PRIMARY KEY (num_bordereau),
  KEY fk_livraison_fournisseur (id_fournisseur),
  CONSTRAINT fk_livraison_fournisseur FOREIGN KEY (id_fournisseur) REFERENCES fournisseur (id_fournisseur)
) ENGINE=InnoDB;
