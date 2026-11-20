-- =====================================================================
-- Dream Travel - Fichier 02 : donnees de test (V1 corrigees)
-- A executer apres 01_schema.sql. Ordre = dependances des cles etrangeres.
-- Les lignes marquees "CORRIGE" ou "AJOUT" different de votre dump V1.
-- =====================================================================
USE dream_travel_test;
SET NAMES utf8mb4;

INSERT INTO entreprise (id_entreprise, nom_commercial, telephone, email, logo) VALUES
('ENT-01X', 'Blue Bird Express', '+237 222 20 30 40', 'contact@bluebird-express.example', NULL);

INSERT INTO ville (id_ville, nom_ville, region, pays) VALUES
('VIL-CM01', 'Yaoundé',   'Centre', 'Cameroun'),
('VIL-CM02', 'Bafoussam', 'Ouest',  'Cameroun'),
('VIL-CM03', 'Dschang',   'Ouest',  'Cameroun');

-- est_siege : 'Oui' -> 1, 'Non' -> 0. CORRIGE : adresse du siege = Essos (rapport, 1.1).
INSERT INTO agence (id_agence, id_entreprise, id_ville, nom_agence, adresse, localisation, est_siege) VALUES
('AGE-YDE01', 'ENT-01X', 'VIL-CM01', 'Blue Bird Express - Yaoundé',   'Essos',        'Yaoundé',   1),
('AGE-BFM01', 'ENT-01X', 'VIL-CM02', 'Blue Bird Express Bafoussam',   'Centre-ville', 'Bafoussam', 0),
('AGE-DSG01', 'ENT-01X', 'VIL-CM03', 'Blue Bird Express - Dschang',   'Centre-ville', 'Dschang',   0);

INSERT INTO role (id_role, libelle, description, portee) VALUES
('ROL-ADM01', 'Administrateur',  'Administration générale de la plateforme', 'ENTREPRISE'),
('ROL-CAG01', 'Chef d''agence',  'Supervision des opérations de l''agence',  'AGENCE'),
('ROL-REC01', 'Réceptionniste',  'Accueil et gestion des réservations',      'AGENCE'),
('ROL-CPT01', 'Comptable',       'Suivi des encaissements et de la caisse',  'AGENCE');

-- AJOUT : EMP-CH01..03 = employes exercant la fonction de chauffeur (RG25).
-- Hash factices : ces comptes n'ont pas de role applicatif et ne se connectent pas.
INSERT INTO employe (id_employe, id_agence, nom, prenom, email, mot_de_passe_hash, date_creation, derniere_connexion) VALUES
('EMP-YD07', 'AGE-YDE01', 'NGONO',   'Mireille', 'mireille.ngano@example.test',  '$2y$EXEMPLE_HASH_01', '2025-01-10', '2026-11-12 08:15:00'),
('EMP-BF12', 'AGE-BFM01', 'FOTSO',   'Armand',   'armand.fotso@example.test',    '$2y$EXEMPLE_HASH_02', '2025-02-15', '2026-11-12 07:50:00'),
('EMP-YD19', 'AGE-YDE01', 'MBARGA',  'Chantal',  'chantal.mbarga@example.test',  '$2y$EXEMPLE_HASH_03', '2025-03-01', '2026-11-11 17:20:00'),
('EMP-DS23', 'AGE-DSG01', 'TCHOUPO', 'Serge',    'serge.tchoupo@example.test',   '$2y$EXEMPLE_HASH_04', '2025-04-05', '2026-11-12 06:55:00'),
('EMP-CH01', 'AGE-YDE01', 'ESSOMBA', 'Patrick',  'patrick.essomba@example.test', '$2y$AUCUN_ACCES_CH01', '2025-01-15', NULL),
('EMP-CH02', 'AGE-YDE01', 'KAMDEM',  'Brice',    'brice.kamdem@example.test',    '$2y$AUCUN_ACCES_CH02', '2025-01-15', NULL),
('EMP-CH03', 'AGE-BFM01', 'ABENA',   'Pauline',  'pauline.abena@example.test',   '$2y$AUCUN_ACCES_CH03', '2025-02-20', NULL);

-- AJOUT : EMP-YD19 exerce aussi le role Comptable car il administre une session de caisse (RG67).
INSERT INTO employe_role (id_employe, id_role, date_attribution) VALUES
('EMP-YD07', 'ROL-ADM01', '2025-01-10'),
('EMP-YD07', 'ROL-CAG01', '2025-01-10'),
('EMP-BF12', 'ROL-CAG01', '2025-02-15'),
('EMP-YD19', 'ROL-REC01', '2025-03-01'),
('EMP-YD19', 'ROL-CPT01', '2025-03-01'),
('EMP-DS23', 'ROL-REC01', '2025-04-05');

-- AJOUT : (AGE-DSG01, ROL-REC01) car EMP-DS23 est receptionniste a Dschang (RG16). Telephone fictif.
INSERT INTO agence_role (id_agence, id_role, telephone_professionnel) VALUES
('AGE-YDE01', 'ROL-ADM01', '+237 699 10 20 01'),
('AGE-YDE01', 'ROL-CAG01', '+237 699 10 20 02'),
('AGE-YDE01', 'ROL-REC01', '+237 699 10 20 03'),
('AGE-YDE01', 'ROL-CPT01', '+237 699 10 20 04'),
('AGE-BFM01', 'ROL-CAG01', '+237 677 20 30 02'),
('AGE-DSG01', 'ROL-REC01', '+237 655 40 50 03');

INSERT INTO itineraire (id_itineraire, libelle, distance_totale_km, duree_estimee_min, trace_geographique, statut) VALUES
('ITI-YD-BF', 'Yaoundé - Bafoussam',    290, 300, 'CM-N10', 'ACTIF'),
('ITI-YDDS',  'Yaoundé - Dschang',      290, 330, 'CM-N4',  'ACTIF'),
('ITI-BF-DS', 'Bafoussam - Dschang',     60,  90, 'CM-N6',  'ACTIF');

INSERT INTO agence_itineraire (id_agence, id_itineraire, date_debut_exploitation) VALUES
('AGE-YDE01', 'ITI-YD-BF', '2025-01-01'),
('AGE-YDE01', 'ITI-YDDS',  '2025-01-01'),
('AGE-BFM01', 'ITI-YD-BF', '2025-01-01'),
('AGE-BFM01', 'ITI-BF-DS', '2025-01-01'),
('AGE-DSG01', 'ITI-YDDS',  '2025-01-01');

INSERT INTO vehicule (immatriculation, id_entreprise, marque, modele, capacite_totale, classe_voyage, etat_operationnel) VALUES
('CE-123-AA', 'ENT-01X', 'Toyota',        'Coaster',  30, 'VIP',      'OPERATIONNEL'),
('LT-456-BB', 'ENT-01X', 'Mercedes-Benz', 'Sprinter', 18, 'STANDARD', 'OPERATIONNEL'),
('OU-789-CC', 'ENT-01X', 'Mitsubishi',    'Rosa',     25, 'STANDARD', 'EN MAINTENANCE');

INSERT INTO escale (id_escale, id_ville, nom_escale, autorise_embarquement, autorise_debarquement) VALUES
('ESC-YDE01', 'VIL-CM01', 'Agence de Yaoundé',   1, 1),
('ESC-BFM01', 'VIL-CM02', 'Agence de Bafoussam', 1, 1),
('ESC-DSG01', 'VIL-CM03', 'Agence de Dschang',   1, 1);

INSERT INTO chauffeur (id_chauffeur, id_employe, nom, prenom, telephone, numero_permis, categorie_permis, statut) VALUES
('CHF-24K', 'EMP-CH01', 'ESSOMBA', 'Patrick', '+237 690 11 22 33', 'PERMIS-CM-1001', 'D', 'DISPONIBLE'),
('CHF-31M', 'EMP-CH02', 'KAMDEM',  'Brice',   '+237 677 22 33 44', 'PERMIS-CM-1002', 'D', 'DISPONIBLE'),
('CHF-48P', 'EMP-CH03', 'ABENA',   'Pauline', '+237 655 33 44 55', 'PERMIS-CM-1003', 'D', 'DISPONIBLE');

-- CORRIGE : VYG-26A01 arrivee 11:00 -> 12:00 (passage a Bafoussam prevu a 12:00 ; 300 min de trajet).
-- CORRIGE : VYG-26A03 arrivee 12:00 -> 09:30 (Bafoussam-Dschang = 90 min).
INSERT INTO voyage (id_voyage, id_itineraire, immatriculation, date_voyage, heure_depart_prevue, heure_arrivee_prevue, heure_depart_reelle, heure_arrivee_reelle, statut) VALUES
('VYG-26A01', 'ITI-YD-BF', 'CE-123-AA', '2026-11-20', '07:00:00', '12:00:00', NULL, NULL, 'PROGRAMME'),
('VYG-26A02', 'ITI-YDDS',  'LT-456-BB', '2026-11-20', '06:30:00', '11:30:00', NULL, NULL, 'PROGRAMME'),
('VYG-26A03', 'ITI-BF-DS', 'CE-123-AA', '2026-11-21', '08:00:00', '09:30:00', NULL, NULL, 'PROGRAMME');

-- V1 : voyage.id_chauffeur -> table associative
INSERT INTO voyage_chauffeur (id_voyage, id_chauffeur) VALUES
('VYG-26A01', 'CHF-24K'),
('VYG-26A02', 'CHF-31M'),
('VYG-26A03', 'CHF-48P');

-- AJOUT : VYG-26A03 n'avait que le depart ; l'arrivee a Dschang est la derniere escale (RG23).
INSERT INTO voyage_escale (id_voyage, id_escale, ordre_passage, heure_passage_prevue) VALUES
('VYG-26A01', 'ESC-YDE01', 1, '07:00:00'),
('VYG-26A01', 'ESC-BFM01', 2, '12:00:00'),
('VYG-26A02', 'ESC-YDE01', 1, '06:30:00'),
('VYG-26A02', 'ESC-DSG01', 2, '11:30:00'),
('VYG-26A03', 'ESC-BFM01', 1, '08:00:00'),
('VYG-26A03', 'ESC-DSG01', 2, '09:30:00');

INSERT INTO tarif (id_tarif, id_entreprise, categorie_tarif, montant_fcfa, date_debut_validite, date_fin_validite, statut) VALUES
('TAR-STD01', 'ENT-01X', 'STANDARD', 5000, '2026-01-01', '2026-12-31', 'ACTIF'),
('TAR-VIP01', 'ENT-01X', 'VIP',      8000, '2026-01-01', '2026-12-31', 'ACTIF'),
('TAR-ETU01', 'ENT-01X', 'ETUDIANT', 4000, '2026-01-01', '2026-12-31', 'ACTIF');

INSERT INTO client (id_client, nom, prenom, telephone, type_piece_identite, numero_piece_identite, date_creation) VALUES
('CLI-7F2K', 'MANGA', 'Clarisse', '+237 690 10 20 30', 'CNI',       'CM-CL-0001', '2026-10-01'),
('CLI-8M4Q', 'NANA',  'Fabrice',  '+237 677 11 22 33', 'CNI',       'CM-CL-0002', '2026-10-03'),
('CLI-3P9A', 'KOUAM', 'Aicha',    '+237 655 44 55 66', 'PASSEPORT', 'CM-P-0003',  '2026-10-05'),
('CLI-6R2B', 'BELLO', 'Ibrahim',  '+237 699 77 88 99', 'CNI',       'CM-CL-0004', '2026-10-08');

-- CORRIGE : RES-26X02 payee (5000) donc CONFIRMEE (RG42, billet BIL-26A03 emis plus bas).
INSERT INTO reservation (id_reservation, id_client, id_voyage, id_employe, date_heure_reservation, date_expiration, montant_total_fcfa, statut) VALUES
('RES-26X01', 'CLI-7F2K', 'VYG-26A01', 'EMP-YD19', '2026-11-12 08:30:00', '2026-11-12 12:30:00', 8000, 'CONFIRMEE'),
('RES-26X02', 'CLI-8M4Q', 'VYG-26A02', 'EMP-DS23', '2026-11-12 09:10:00', '2026-11-12 13:10:00', 5000, 'CONFIRMEE'),
('RES-26X03', 'CLI-3P9A', 'VYG-26A03', 'EMP-BF12', '2026-11-12 10:00:00', '2026-11-12 14:00:00', 8000, 'CONFIRMEE');

-- AJOUT : id_voyage, numero_place et montant_fcfa (les places etaient seulement sur les billets en V1).
INSERT INTO reservation_voyageur (id_reservation, numero_voyageur, id_voyage, nom_voyageur, prenom_voyageur, type_piece, numero_piece, numero_place, montant_fcfa) VALUES
('RES-26X01', 1, 'VYG-26A01', 'MANGA', 'Clarisse', 'CNI',       'CM-CL-0001', 12, 8000),
('RES-26X02', 1, 'VYG-26A02', 'NANA',  'Fabrice',  'CNI',       'CM-CL-0002',  7, 5000),
('RES-26X03', 1, 'VYG-26A03', 'KOUAM', 'Aicha',    'PASSEPORT', 'CM-P-0003',   5, 8000);

-- CORRIGE : totaux = somme des paiements encaisses (CI03) : 7200 + 5000 = 12200 ; 7000.
INSERT INTO session_de_caisse (id_session, id_agence, id_employe, date_heure_ouverture, date_heure_fermeture, total_encaisse_fcfa, statut_session) VALUES
('SES-YD01', 'AGE-YDE01', 'EMP-YD19', '2026-11-12 07:30:00', '2026-11-12 17:00:00', 12200, 'FERMEE'),
('SES-BF02', 'AGE-BFM01', 'EMP-BF12', '2026-11-12 07:00:00', NULL,                  7000, 'OUVERTE');

-- remise : 10 = 10 % pour POURCENTAGE, 1000 = 1000 FCFA pour MONTANT.
INSERT INTO promotion (id_promotion, libelle, mode_remise, remise, date_debut, date_fin, public_cible) VALUES
('PRO-26R01', 'Promo rentrée',       'POURCENTAGE',   10, '2026-09-01', '2026-12-31', 'Etudiants'),
('PRO-26R02', 'Réduction fidélité',  'MONTANT',     1000, '2026-10-01', '2026-12-31', 'Clients fidèles');

-- CORRIGE : montant_fcfa = montant net encaisse (RG45) ; la remise est sur le paiement
-- (paiement_promotion supprimee). PAY-26A01 : 8000-800 ; PAY-26A02 : 8000 -> 5000 (= RES-26X02) ; PAY-26A03 : 8000-1000.
INSERT INTO paiement (id_paiement, id_reservation, id_session, id_promotion, montant_remise_fcfa, montant_fcfa, moyen_paiement, date_heure_paiement, statut_paiement) VALUES
('PAY-26A01', 'RES-26X01', 'SES-YD01', 'PRO-26R01',  800, 7200, 'ESPECES',      '2026-11-12 08:45:00', 'ENCAISSE'),
('PAY-26A02', 'RES-26X02', 'SES-YD01', NULL,        NULL, 5000, 'MOBILE MONEY', '2026-11-12 09:00:00', 'ENCAISSE'),
('PAY-26A03', 'RES-26X03', 'SES-BF02', 'PRO-26R02', 1000, 7000, 'ORANGE MONEY', '2026-11-12 10:15:00', 'ENCAISSE');

-- AJOUT : BIL-26A03 (paiement PAY-26A02 encaisse -> billet). montant = prix du billet avant remise.
INSERT INTO billet (id_billet, id_reservation, numero_voyageur, id_paiement, numero_place, montant_fcfa, date_emission) VALUES
('BIL-26A01', 'RES-26X01', 1, 'PAY-26A01', 12, 8000, '2026-11-12 08:50:00'),
('BIL-26A02', 'RES-26X03', 1, 'PAY-26A03',  5, 8000, '2026-11-12 10:20:00'),
('BIL-26A03', 'RES-26X02', 1, 'PAY-26A02',  7, 5000, '2026-11-12 09:05:00');

INSERT INTO service (id_service, id_entreprise, libelle_service) VALUES
('SRV-CL01', 'ENT-01X', 'Service clientèle'),
('SRV-EX02', 'ENT-01X', 'Exploitation et transport'),
('SRV-CP03', 'ENT-01X', 'Comptabilité');

-- etat_materiel_remis (V1) renomme etat_materiel_remise (nom du rapport).
INSERT INTO colis (id_colis, id_client_expediteur, nom_destinataire, telephone_destinataire, description_contenu, valeur_declaree_fcfa, tarif_suggere_fcfa, montant_confirme_fcfa, date_heure_depot, date_heure_arrivee, date_heure_retrait, etat_materiel_remise, statut_colis) VALUES
('COL-26A01', 'CLI-7F2K', 'Pauline Abanda', '+237 699 12 34 56', 'Documents administratifs', 25000, 3000, 2500, '2026-11-12 09:00:00', NULL,                  NULL,                  'BON ETAT', 'EN TRANSIT'),
('COL-26A02', 'CLI-8M4Q', 'Jean Moukouri',  '+237 677 23 45 67', 'Vêtements',                 40000, 5000, 4500, '2026-11-11 14:00:00', '2026-11-12 10:00:00', NULL,                  'BON ETAT', 'ARRIVE'),
('COL-26A03', 'CLI-3P9A', 'Esther Nguema',  '+237 655 34 56 78', 'Petit colis emballé',       15000, 2500, 2500, '2026-11-10 08:00:00', '2026-11-11 16:00:00', '2026-11-12 11:00:00', 'BON ETAT', 'RETIRE');

INSERT INTO reclamation (id_reclamation, id_client, id_colis, id_service, date_heure_enregistrement, motif, description, date_heure_transmission, statut_transmission, date_heure_traitement) VALUES
('REC-26A01', 'CLI-7F2K', 'COL-26A01', 'SRV-CL01', '2026-11-12 11:00:00', 'Retard de livraison',    'Le colis est toujours en transit',        '2026-11-12 11:15:00', 'TRANSMISE',  NULL),
('REC-26A02', 'CLI-8M4Q', NULL,        'SRV-CL01', '2026-11-12 12:00:00', 'Information réservation', 'Demande de précision sur les horaires.', NULL,                  'EN ATTENTE', NULL);

INSERT INTO fournisseur (id_fournisseur, raison_sociale, contact) VALUES
('FOU-01A', 'ETS Mbarga Fournitures',    '+237 690 90 10 10'),
('FOU-02B', 'Africa Bus Services SARL',  '+237 677 80 70 60');

INSERT INTO livraison (num_bordereau, id_fournisseur, date_livraison, designation) VALUES
('BL-2026-001', 'FOU-01A', '2026-11-05', 'Fournitures de bureau'),
('BL-2026-002', 'FOU-02B', '2026-11-08', 'Pièces détachées pour véhicules');

-- CORRIGE : AUD-26A01 -> EMP-YD19 (c'est lui qui a cree RES-26X01, pas EMP-YD07).
INSERT INTO journal_audit (id_audit, id_employe, id_agence, type_objet, identifiant_objet, action, date_heure_action, resultat, description_changement) VALUES
('AUD-26A01', 'EMP-YD19', 'AGE-YDE01', 'RESERVATION', 'RES-26X01', 'CREATION',       '2026-11-12 08:30:00', 'SUCCES', 'Création de la réservation de test 1'),
('AUD-26A02', 'EMP-YD19', 'AGE-YDE01', 'PAIEMENT',    'PAY-26A01', 'ENREGISTREMENT', '2026-11-12 08:45:00', 'SUCCES', 'Enregistrement d''un paiement encaissé'),
('AUD-26A03', 'EMP-BF12', 'AGE-BFM01', 'VOYAGE',      'VYG-26A01', 'CONSULTATION',   '2026-11-12 09:00:00', 'SUCCES', 'Consultation du planning');
