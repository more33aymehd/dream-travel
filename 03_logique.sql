-- =====================================================================
-- Dream Travel - Fichier 03 : logique metier dans la base
-- Procedures stockees, triggers, vues, evenement planifie.
-- A executer apres 01_schema.sql et 02_donnees.sql (le fichier 01 supprime
-- les triggers avec les tables : pour repartir de zero, rejouer 01, 02, 03).
--
-- Convention : l'application PHP execute, apres chaque connexion d'un agent,
--     SET @app_employe_id = 'EMP-YD19';
-- Le journal d'audit (RG70-RG72) utilise cette variable pour savoir QUI agit.
-- Sans elle (ex. administration directe en SQL), rien n'est journalise.
-- =====================================================================
USE dream_travel;

DELIMITER $$

-- ---------------------------------------------------------------------
-- 0. UTILITAIRES
-- ---------------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_audit$$
CREATE PROCEDURE sp_audit(IN p_type VARCHAR(50), IN p_objet VARCHAR(20),
                          IN p_action VARCHAR(50), IN p_description TEXT)
BEGIN
  DECLARE v_agence VARCHAR(20) DEFAULT NULL;
  IF @app_employe_id IS NOT NULL THEN
    SELECT id_agence INTO v_agence FROM employe WHERE id_employe = @app_employe_id;
    IF v_agence IS NOT NULL THEN
      INSERT INTO journal_audit (id_audit, id_employe, id_agence, type_objet, identifiant_objet,
                                 action, date_heure_action, resultat, description_changement)
      VALUES (CONCAT('AUD-', UPPER(LEFT(REPLACE(UUID(), '-', ''), 14))),
              @app_employe_id, v_agence, p_type, p_objet, p_action, NOW(), 'SUCCES', p_description);
    END IF;
  END IF;
END$$

-- Un vehicule ne peut etre programme que s'il est operationnel et libre sur le creneau.
DROP PROCEDURE IF EXISTS sp_verifier_voyage$$
CREATE PROCEDURE sp_verifier_voyage(IN p_ignore VARCHAR(20), IN p_immat VARCHAR(20),
                                    IN p_date DATE, IN p_depart TIME, IN p_arrivee TIME)
BEGIN
  DECLARE v_etat VARCHAR(20) DEFAULT NULL;
  SELECT etat_operationnel INTO v_etat FROM vehicule WHERE immatriculation = p_immat;
  IF v_etat <> 'OPERATIONNEL' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicule non operationnel : voyage impossible';
  END IF;
  IF EXISTS (SELECT 1 FROM voyage v
             WHERE v.immatriculation = p_immat AND v.date_voyage = p_date
               AND v.statut <> 'ANNULE' AND v.id_voyage <> COALESCE(p_ignore, '')
               AND v.heure_depart_prevue < p_arrivee AND v.heure_arrivee_prevue > p_depart) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicule deja affecte a un voyage sur ce creneau';
  END IF;
END$$

-- La place doit exister dans le vehicule (<= capacite) et le voyage doit etre programme.
DROP PROCEDURE IF EXISTS sp_verifier_place$$
CREATE PROCEDURE sp_verifier_place(IN p_voyage VARCHAR(20), IN p_place INT)
BEGIN
  DECLARE v_cap INT DEFAULT NULL;
  DECLARE v_statut VARCHAR(20) DEFAULT NULL;
  SELECT ve.capacite_totale, vo.statut INTO v_cap, v_statut
    FROM voyage vo JOIN vehicule ve ON ve.immatriculation = vo.immatriculation
   WHERE vo.id_voyage = p_voyage;
  IF v_statut <> 'PROGRAMME' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le voyage n''est plus ouvert a la reservation';
  END IF;
  IF p_place IS NOT NULL AND p_place > v_cap THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Numero de place superieur a la capacite du vehicule';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- 1. TRIGGERS : controle d'integrite (comme trg_verif_note_insert du cours)
-- ---------------------------------------------------------------------

DROP TRIGGER IF EXISTS trg_voyage_bi$$
CREATE TRIGGER trg_voyage_bi BEFORE INSERT ON voyage FOR EACH ROW
BEGIN
  CALL sp_verifier_voyage(NEW.id_voyage, NEW.immatriculation, NEW.date_voyage,
                          NEW.heure_depart_prevue, NEW.heure_arrivee_prevue);
END$$

DROP TRIGGER IF EXISTS trg_voyage_bu$$
CREATE TRIGGER trg_voyage_bu BEFORE UPDATE ON voyage FOR EACH ROW
BEGIN
  IF NEW.statut <> 'ANNULE' AND NOT (NEW.immatriculation <=> OLD.immatriculation
        AND NEW.date_voyage <=> OLD.date_voyage
        AND NEW.heure_depart_prevue <=> OLD.heure_depart_prevue
        AND NEW.heure_arrivee_prevue <=> OLD.heure_arrivee_prevue) THEN
    CALL sp_verifier_voyage(NEW.id_voyage, NEW.immatriculation, NEW.date_voyage,
                            NEW.heure_depart_prevue, NEW.heure_arrivee_prevue);
  END IF;
END$$

-- Reservation : voyage programme, expiration au plus tard au depart.
DROP TRIGGER IF EXISTS trg_reservation_bi$$
CREATE TRIGGER trg_reservation_bi BEFORE INSERT ON reservation FOR EACH ROW
BEGIN
  DECLARE v_depart DATETIME DEFAULT NULL;
  DECLARE v_statut VARCHAR(20) DEFAULT NULL;
  SELECT TIMESTAMP(date_voyage, heure_depart_prevue), statut INTO v_depart, v_statut
    FROM voyage WHERE id_voyage = NEW.id_voyage;
  IF v_statut <> 'PROGRAMME' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le voyage n''est plus ouvert a la reservation';
  END IF;
  IF NEW.date_expiration > v_depart THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'L''expiration ne peut pas depasser l''heure de depart';
  END IF;
END$$

-- Transitions d'etat interdites (une reservation expiree/annulee est definitive,
-- une reservation payee ne repasse pas en attente).
DROP TRIGGER IF EXISTS trg_reservation_bu$$
CREATE TRIGGER trg_reservation_bu BEFORE UPDATE ON reservation FOR EACH ROW
BEGIN
  IF OLD.statut IN ('EXPIREE','ANNULEE') AND NEW.statut <> OLD.statut THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation expiree ou annulee : statut definitif';
  END IF;
  IF OLD.statut = 'CONFIRMEE' AND NEW.statut IN ('EN ATTENTE','EXPIREE') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation payee : transition de statut interdite';
  END IF;
END$$

-- Expiration / annulation : les places sont liberees (actif = 0) -> elles redeviennent reservables.
DROP TRIGGER IF EXISTS trg_reservation_au$$
CREATE TRIGGER trg_reservation_au AFTER UPDATE ON reservation FOR EACH ROW
BEGIN
  IF NEW.statut IN ('EXPIREE','ANNULEE') AND OLD.statut <> NEW.statut THEN
    UPDATE reservation_voyageur SET actif = 0 WHERE id_reservation = NEW.id_reservation;
  END IF;
  IF OLD.statut <> NEW.statut THEN
    CALL sp_audit('RESERVATION', NEW.id_reservation, 'CHANGEMENT STATUT',
                  CONCAT(OLD.statut, ' -> ', NEW.statut));
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_reservation_ai$$
CREATE TRIGGER trg_reservation_ai AFTER INSERT ON reservation FOR EACH ROW
BEGIN
  CALL sp_audit('RESERVATION', NEW.id_reservation, 'CREATION',
                CONCAT('Voyage ', NEW.id_voyage, ', client ', NEW.id_client));
END$$

DROP TRIGGER IF EXISTS trg_rv_bi$$
CREATE TRIGGER trg_rv_bi BEFORE INSERT ON reservation_voyageur FOR EACH ROW
BEGIN
  CALL sp_verifier_place(NEW.id_voyage, NEW.numero_place);
END$$

DROP TRIGGER IF EXISTS trg_rv_bu$$
CREATE TRIGGER trg_rv_bu BEFORE UPDATE ON reservation_voyageur FOR EACH ROW
BEGIN
  IF NOT (NEW.numero_place <=> OLD.numero_place) THEN
    CALL sp_verifier_place(NEW.id_voyage, NEW.numero_place);
  END IF;
END$$

-- Paiement (RG39-RG45) : enregistre apres encaissement, dans une session OUVERTE,
-- pour une reservation en attente et non expiree, montant + remise = total.
DROP TRIGGER IF EXISTS trg_paiement_bi$$
CREATE TRIGGER trg_paiement_bi BEFORE INSERT ON paiement FOR EACH ROW
BEGIN
  DECLARE v_session VARCHAR(20) DEFAULT NULL;
  DECLARE v_statut VARCHAR(20) DEFAULT NULL;
  DECLARE v_total INT DEFAULT NULL;
  DECLARE v_exp DATETIME DEFAULT NULL;
  DECLARE v_debut DATE DEFAULT NULL;
  DECLARE v_fin DATE DEFAULT NULL;

  IF NEW.statut_paiement <> 'ENCAISSE' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un paiement est enregistre uniquement une fois encaisse';
  END IF;
  SELECT statut_session INTO v_session FROM session_de_caisse WHERE id_session = NEW.id_session;
  IF v_session <> 'OUVERTE' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La session de caisse n''est pas ouverte';
  END IF;

  IF NEW.id_reservation IS NOT NULL THEN
    SELECT statut, montant_total_fcfa, date_expiration INTO v_statut, v_total, v_exp
      FROM reservation WHERE id_reservation = NEW.id_reservation;
    IF v_statut <> 'EN ATTENTE' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La reservation n''est pas en attente de paiement';
    END IF;
    IF NEW.date_heure_paiement > v_exp THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation expiree au moment du paiement';
    END IF;
    IF NEW.montant_fcfa + COALESCE(NEW.montant_remise_fcfa, 0) <> v_total THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Montant + remise different du total de la reservation';
    END IF;
  END IF;

  IF NEW.id_promotion IS NOT NULL THEN
    SELECT date_debut, date_fin INTO v_debut, v_fin FROM promotion WHERE id_promotion = NEW.id_promotion;
    IF DATE(NEW.date_heure_paiement) NOT BETWEEN v_debut AND v_fin THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Promotion hors periode de validite';
    END IF;
  END IF;
END$$

-- Un paiement enregistre ne change plus (sauf son statut : annulation / remboursement).
DROP TRIGGER IF EXISTS trg_paiement_bu$$
CREATE TRIGGER trg_paiement_bu BEFORE UPDATE ON paiement FOR EACH ROW
BEGIN
  IF NOT (NEW.montant_fcfa = OLD.montant_fcfa AND NEW.id_session = OLD.id_session
          AND NEW.id_reservation <=> OLD.id_reservation
          AND NEW.id_promotion <=> OLD.id_promotion
          AND NEW.montant_remise_fcfa <=> OLD.montant_remise_fcfa) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Paiement non modifiable : seul le statut peut changer';
  END IF;
END$$

-- CI03 / RG69 : total de la session = somme des paiements encaisses.
DROP TRIGGER IF EXISTS trg_paiement_ai$$
CREATE TRIGGER trg_paiement_ai AFTER INSERT ON paiement FOR EACH ROW
BEGIN
  UPDATE session_de_caisse
     SET total_encaisse_fcfa = (SELECT COALESCE(SUM(montant_fcfa), 0) FROM paiement
                                WHERE id_session = NEW.id_session AND statut_paiement = 'ENCAISSE')
   WHERE id_session = NEW.id_session;
  CALL sp_audit('PAIEMENT', NEW.id_paiement, 'ENREGISTREMENT',
                CONCAT(NEW.montant_fcfa, ' FCFA par ', NEW.moyen_paiement));
END$$

DROP TRIGGER IF EXISTS trg_paiement_au$$
CREATE TRIGGER trg_paiement_au AFTER UPDATE ON paiement FOR EACH ROW
BEGIN
  IF OLD.statut_paiement <> NEW.statut_paiement THEN
    UPDATE session_de_caisse
       SET total_encaisse_fcfa = (SELECT COALESCE(SUM(montant_fcfa), 0) FROM paiement
                                  WHERE id_session = NEW.id_session AND statut_paiement = 'ENCAISSE')
     WHERE id_session = NEW.id_session;
    CALL sp_audit('PAIEMENT', NEW.id_paiement, 'CHANGEMENT STATUT',
                  CONCAT(OLD.statut_paiement, ' -> ', NEW.statut_paiement));
  END IF;
END$$

-- Billet (RG38, RG42) : paiement encaisse lie a la MEME reservation ; la place est
-- toujours recopiee depuis reservation_voyageur (source de verite).
DROP TRIGGER IF EXISTS trg_billet_bi$$
CREATE TRIGGER trg_billet_bi BEFORE INSERT ON billet FOR EACH ROW
BEGIN
  DECLARE v_resa VARCHAR(20) DEFAULT NULL;
  DECLARE v_statut VARCHAR(20) DEFAULT NULL;
  DECLARE v_place INT DEFAULT NULL;
  DECLARE v_actif TINYINT DEFAULT NULL;
  SELECT id_reservation, statut_paiement INTO v_resa, v_statut
    FROM paiement WHERE id_paiement = NEW.id_paiement;
  IF v_statut <> 'ENCAISSE' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Billet impossible : paiement non encaisse';
  END IF;
  IF v_resa IS NULL OR v_resa <> NEW.id_reservation THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Billet impossible : paiement non associe a cette reservation';
  END IF;
  SELECT numero_place, actif INTO v_place, v_actif FROM reservation_voyageur
   WHERE id_reservation = NEW.id_reservation AND numero_voyageur = NEW.numero_voyageur;
  IF v_place IS NULL OR v_actif = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Billet impossible : voyageur sans place active';
  END IF;
  SET NEW.numero_place = v_place;
END$$

DROP TRIGGER IF EXISTS trg_billet_ai$$
CREATE TRIGGER trg_billet_ai AFTER INSERT ON billet FOR EACH ROW
BEGIN
  CALL sp_audit('BILLET', NEW.id_billet, 'EMISSION', CONCAT('Place ', NEW.numero_place));
END$$

-- Session de caisse : ouverture / fermeture journalisees.
DROP TRIGGER IF EXISTS trg_session_ai$$
CREATE TRIGGER trg_session_ai AFTER INSERT ON session_de_caisse FOR EACH ROW
BEGIN
  CALL sp_audit('SESSION_DE_CAISSE', NEW.id_session, 'OUVERTURE', NULL);
END$$

DROP TRIGGER IF EXISTS trg_session_au$$
CREATE TRIGGER trg_session_au AFTER UPDATE ON session_de_caisse FOR EACH ROW
BEGIN
  IF OLD.statut_session <> NEW.statut_session THEN
    CALL sp_audit('SESSION_DE_CAISSE', NEW.id_session, 'FERMETURE',
                  CONCAT('Total encaisse : ', NEW.total_encaisse_fcfa, ' FCFA'));
  END IF;
END$$

-- Tracabilite (3.5.3) : le journal d'audit est immuable.
DROP TRIGGER IF EXISTS trg_audit_bu$$
CREATE TRIGGER trg_audit_bu BEFORE UPDATE ON journal_audit FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d''audit en lecture seule';
END$$

DROP TRIGGER IF EXISTS trg_audit_bd$$
CREATE TRIGGER trg_audit_bd BEFORE DELETE ON journal_audit FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d''audit en lecture seule';
END$$

-- ---------------------------------------------------------------------
-- 2. PROCEDURES STOCKEES : les operations metier appelees par le PHP
-- ---------------------------------------------------------------------

-- Ouverture de caisse. RG67 : reservee aux roles Comptable et Chef d'agence.
DROP PROCEDURE IF EXISTS sp_ouvrir_session$$
CREATE PROCEDURE sp_ouvrir_session(IN p_employe VARCHAR(20), OUT p_id_session VARCHAR(20))
BEGIN
  DECLARE v_agence VARCHAR(20) DEFAULT NULL;
  SELECT id_agence INTO v_agence FROM employe WHERE id_employe = p_employe;
  IF v_agence IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Employe introuvable';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM employe_role er JOIN role r ON r.id_role = er.id_role
                 WHERE er.id_employe = p_employe AND r.libelle IN ('Comptable', 'Chef d''agence')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acces a la caisse refuse : role insuffisant';
  END IF;
  IF EXISTS (SELECT 1 FROM session_de_caisse WHERE id_employe = p_employe AND statut_session = 'OUVERTE') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cet employe a deja une session de caisse ouverte';
  END IF;
  SET p_id_session = CONCAT('SES-', UPPER(LEFT(REPLACE(UUID(), '-', ''), 12)));
  INSERT INTO session_de_caisse (id_session, id_agence, id_employe, date_heure_ouverture,
                                 total_encaisse_fcfa, statut_session)
  VALUES (p_id_session, v_agence, p_employe, NOW(), 0, 'OUVERTE');
END$$

-- Fermeture : le total est recalcule a partir des paiements (CI03).
DROP PROCEDURE IF EXISTS sp_fermer_session$$
CREATE PROCEDURE sp_fermer_session(IN p_id_session VARCHAR(20), IN p_employe VARCHAR(20))
BEGIN
  DECLARE v_statut VARCHAR(20) DEFAULT NULL;
  DECLARE v_emp VARCHAR(20) DEFAULT NULL;
  SELECT statut_session, id_employe INTO v_statut, v_emp FROM session_de_caisse WHERE id_session = p_id_session;
  IF v_statut IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Session introuvable';
  END IF;
  IF v_statut <> 'OUVERTE' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Session deja fermee';
  END IF;
  IF v_emp <> p_employe THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seul l''employe qui administre la session peut la fermer';
  END IF;
  UPDATE session_de_caisse
     SET total_encaisse_fcfa = (SELECT COALESCE(SUM(montant_fcfa), 0) FROM paiement
                                WHERE id_session = p_id_session AND statut_paiement = 'ENCAISSE'),
         date_heure_fermeture = GREATEST(NOW(), DATE_ADD(date_heure_ouverture, INTERVAL 1 SECOND)),
         statut_session = 'FERMEE'
   WHERE id_session = p_id_session;
END$$

-- Etape 1 : creer la reservation (vide). Elle expire au bout de p_duree_min minutes
-- ou au depart du voyage si celui-ci est plus proche.
DROP PROCEDURE IF EXISTS sp_creer_reservation$$
CREATE PROCEDURE sp_creer_reservation(IN p_client VARCHAR(20), IN p_voyage VARCHAR(20),
                                      IN p_employe VARCHAR(20), IN p_duree_min INT,
                                      OUT p_id_reservation VARCHAR(20))
BEGIN
  DECLARE v_depart DATETIME DEFAULT NULL;
  DECLARE v_exp DATETIME;
  SELECT TIMESTAMP(date_voyage, heure_depart_prevue) INTO v_depart FROM voyage WHERE id_voyage = p_voyage;
  IF v_depart IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Voyage introuvable';
  END IF;
  IF v_depart <= NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le voyage est deja parti';
  END IF;
  SET v_exp = LEAST(DATE_ADD(NOW(), INTERVAL COALESCE(p_duree_min, 60) MINUTE), v_depart);
  SET p_id_reservation = CONCAT('RES-', UPPER(LEFT(REPLACE(UUID(), '-', ''), 12)));
  INSERT INTO reservation (id_reservation, id_client, id_voyage, id_employe,
                           date_heure_reservation, date_expiration, montant_total_fcfa, statut)
  VALUES (p_id_reservation, p_client, p_voyage, p_employe, NOW(), v_exp, 0, 'EN ATTENTE');
END$$

-- Etape 2 (autant de fois que de voyageurs) : identite (RG35), place, prix.
-- p_place NULL = premiere place libre. p_categorie NULL = classe du vehicule.
DROP PROCEDURE IF EXISTS sp_ajouter_voyageur$$
CREATE PROCEDURE sp_ajouter_voyageur(IN p_id_reservation VARCHAR(20), IN p_nom VARCHAR(100),
                                     IN p_prenom VARCHAR(100), IN p_type_piece VARCHAR(20),
                                     IN p_numero_piece VARCHAR(50), IN p_place INT,
                                     IN p_categorie VARCHAR(20), OUT p_numero_voyageur INT)
BEGIN
  DECLARE v_statut VARCHAR(20) DEFAULT NULL;
  DECLARE v_exp DATETIME;
  DECLARE v_voyage VARCHAR(20);
  DECLARE v_date DATE;
  DECLARE v_cap INT;
  DECLARE v_classe VARCHAR(20);
  DECLARE v_entreprise VARCHAR(20);
  DECLARE v_prix INT DEFAULT NULL;
  DECLARE v_place INT;
  DECLARE EXIT HANDLER FOR 1062
  BEGIN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cette place est deja reservee sur ce voyage';
  END;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
  SELECT r.statut, r.date_expiration, r.id_voyage, vo.date_voyage, ve.capacite_totale,
         ve.classe_voyage, ve.id_entreprise
    INTO v_statut, v_exp, v_voyage, v_date, v_cap, v_classe, v_entreprise
    FROM reservation r
    JOIN voyage vo ON vo.id_voyage = r.id_voyage
    JOIN vehicule ve ON ve.immatriculation = vo.immatriculation
   WHERE r.id_reservation = p_id_reservation FOR UPDATE;
  IF v_statut IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation introuvable';
  END IF;
  IF v_statut <> 'EN ATTENTE' OR v_exp <= NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation non modifiable (payee, annulee ou expiree)';
  END IF;

  SELECT montant_fcfa INTO v_prix FROM tarif
   WHERE id_entreprise = v_entreprise AND statut = 'ACTIF'
     AND categorie_tarif = COALESCE(p_categorie, v_classe)
     AND v_date BETWEEN date_debut_validite AND date_fin_validite
   ORDER BY date_debut_validite DESC LIMIT 1;
  IF v_prix IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Aucun tarif actif pour cette categorie a cette date';
  END IF;

  SET v_place = p_place;
  IF v_place IS NULL THEN
    SET v_place = 1;
    WHILE v_place <= v_cap AND EXISTS (SELECT 1 FROM reservation_voyageur
          WHERE id_voyage = v_voyage AND actif = 1 AND numero_place = v_place) DO
      SET v_place = v_place + 1;
    END WHILE;
    IF v_place > v_cap THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Voyage complet : aucune place libre';
    END IF;
  END IF;

  SELECT COALESCE(MAX(numero_voyageur), 0) + 1 INTO p_numero_voyageur
    FROM reservation_voyageur WHERE id_reservation = p_id_reservation;
  INSERT INTO reservation_voyageur (id_reservation, numero_voyageur, id_voyage, nom_voyageur,
                                    prenom_voyageur, type_piece, numero_piece, numero_place, montant_fcfa)
  VALUES (p_id_reservation, p_numero_voyageur, v_voyage, p_nom, p_prenom,
          p_type_piece, p_numero_piece, v_place, v_prix);
  UPDATE reservation
     SET montant_total_fcfa = (SELECT SUM(montant_fcfa) FROM reservation_voyageur
                               WHERE id_reservation = p_id_reservation AND actif = 1)
   WHERE id_reservation = p_id_reservation;
  COMMIT;
END$$

-- Etape 3 : enregistrer le paiement deja encaisse, confirmer la reservation et
-- emettre un billet par voyageur (RG42), en une seule transaction.
DROP PROCEDURE IF EXISTS sp_enregistrer_paiement$$
CREATE PROCEDURE sp_enregistrer_paiement(IN p_id_reservation VARCHAR(20), IN p_id_session VARCHAR(20),
                                         IN p_moyen VARCHAR(20), IN p_id_promotion VARCHAR(20),
                                         OUT p_id_paiement VARCHAR(20))
BEGIN
  DECLARE v_total INT DEFAULT NULL;
  DECLARE v_statut VARCHAR(20) DEFAULT NULL;
  DECLARE v_mode VARCHAR(20) DEFAULT NULL;
  DECLARE v_val INT DEFAULT NULL;
  DECLARE v_remise INT DEFAULT NULL;
  DECLARE v_nb INT DEFAULT 0;
  DECLARE v_sans_place INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
  SELECT statut, montant_total_fcfa INTO v_statut, v_total
    FROM reservation WHERE id_reservation = p_id_reservation FOR UPDATE;
  IF v_statut IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation introuvable';
  END IF;
  SELECT COUNT(*), COALESCE(SUM(numero_place IS NULL), 0) INTO v_nb, v_sans_place
    FROM reservation_voyageur WHERE id_reservation = p_id_reservation AND actif = 1;
  IF v_nb = 0 OR v_sans_place > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation sans voyageur ou voyageur sans place';
  END IF;

  IF p_id_promotion IS NOT NULL THEN
    SELECT mode_remise, remise INTO v_mode, v_val FROM promotion WHERE id_promotion = p_id_promotion;
    IF v_mode IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Promotion introuvable';
    END IF;
    IF v_mode = 'POURCENTAGE' THEN
      SET v_remise = FLOOR(v_total * v_val / 100);
    ELSE
      SET v_remise = LEAST(v_val, v_total);
    END IF;
  END IF;

  SET p_id_paiement = CONCAT('PAY-', UPPER(LEFT(REPLACE(UUID(), '-', ''), 12)));
  INSERT INTO paiement (id_paiement, id_reservation, id_session, id_promotion, montant_remise_fcfa,
                        montant_fcfa, moyen_paiement, date_heure_paiement, statut_paiement)
  VALUES (p_id_paiement, p_id_reservation, p_id_session, p_id_promotion, v_remise,
          v_total - COALESCE(v_remise, 0), p_moyen, NOW(), 'ENCAISSE');

  UPDATE reservation SET statut = 'CONFIRMEE' WHERE id_reservation = p_id_reservation;

  INSERT INTO billet (id_billet, id_reservation, numero_voyageur, id_paiement,
                      numero_place, montant_fcfa, date_emission)
  SELECT CONCAT('BIL-', UPPER(LEFT(REPLACE(UUID(), '-', ''), 12))), rv.id_reservation,
         rv.numero_voyageur, p_id_paiement, rv.numero_place, rv.montant_fcfa, NOW()
    FROM reservation_voyageur rv
   WHERE rv.id_reservation = p_id_reservation AND rv.actif = 1;
  COMMIT;
END$$

-- Annulation d'une reservation non payee (le remboursement est hors perimetre).
DROP PROCEDURE IF EXISTS sp_annuler_reservation$$
CREATE PROCEDURE sp_annuler_reservation(IN p_id_reservation VARCHAR(20))
BEGIN
  DECLARE v_statut VARCHAR(20) DEFAULT NULL;
  SELECT statut INTO v_statut FROM reservation WHERE id_reservation = p_id_reservation;
  IF v_statut IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation introuvable';
  END IF;
  IF v_statut <> 'EN ATTENTE' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seule une reservation en attente peut etre annulee ici';
  END IF;
  UPDATE reservation SET statut = 'ANNULEE' WHERE id_reservation = p_id_reservation;
END$$

-- Libere les places des reservations non payees dont le delai est depasse.
DROP PROCEDURE IF EXISTS sp_expirer_reservations$$
CREATE PROCEDURE sp_expirer_reservations()
BEGIN
  UPDATE reservation SET statut = 'EXPIREE'
   WHERE statut = 'EN ATTENTE' AND date_expiration < NOW();
END$$

-- CI02 : changer de siege sans jamais laisser l'entreprise sans siege.
DROP PROCEDURE IF EXISTS sp_definir_siege$$
CREATE PROCEDURE sp_definir_siege(IN p_id_agence VARCHAR(20))
BEGIN
  DECLARE v_ent VARCHAR(20) DEFAULT NULL;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;
  SELECT id_entreprise INTO v_ent FROM agence WHERE id_agence = p_id_agence;
  IF v_ent IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Agence introuvable';
  END IF;
  START TRANSACTION;
  UPDATE agence SET est_siege = 0 WHERE id_entreprise = v_ent AND est_siege = 1;
  UPDATE agence SET est_siege = 1 WHERE id_agence = p_id_agence;
  COMMIT;
END$$

DELIMITER ;

-- Evenement : expiration automatique toutes les 5 minutes.
-- Necessite SET GLOBAL event_scheduler = ON; (sinon appeler la procedure depuis un cron).
DROP EVENT IF EXISTS ev_expirer_reservations;
CREATE EVENT ev_expirer_reservations
  ON SCHEDULE EVERY 5 MINUTE
  DO CALL sp_expirer_reservations();

-- ---------------------------------------------------------------------
-- 3. VUES : lecture simplifiee et securisee (comme Vue_Releves_Notes du cours)
-- ---------------------------------------------------------------------

-- Places libres par voyage (ecran de vente).
CREATE OR REPLACE VIEW v_places_voyage AS
SELECT vo.id_voyage, it.libelle AS itineraire, vo.date_voyage, vo.heure_depart_prevue,
       vo.statut, ve.immatriculation, ve.classe_voyage, ve.capacite_totale,
       COALESCE(SUM(rv.actif = 1), 0)                        AS places_prises,
       ve.capacite_totale - COALESCE(SUM(rv.actif = 1), 0)   AS places_libres
  FROM voyage vo
  JOIN itineraire it ON it.id_itineraire = vo.id_itineraire
  JOIN vehicule ve   ON ve.immatriculation = vo.immatriculation
  LEFT JOIN reservation_voyageur rv ON rv.id_voyage = vo.id_voyage
 GROUP BY vo.id_voyage, it.libelle, vo.date_voyage, vo.heure_depart_prevue, vo.statut,
          ve.immatriculation, ve.classe_voyage, ve.capacite_totale;

-- Fiche voyage : itineraire, vehicule, chauffeurs, escales ordonnees.
CREATE OR REPLACE VIEW v_voyage_detail AS
SELECT vo.id_voyage, it.libelle AS itineraire, vo.date_voyage, vo.heure_depart_prevue,
       vo.heure_arrivee_prevue, vo.statut, ve.immatriculation, ve.marque, ve.modele,
       (SELECT GROUP_CONCAT(CONCAT(c.prenom, ' ', c.nom) SEPARATOR ', ')
          FROM voyage_chauffeur vc JOIN chauffeur c ON c.id_chauffeur = vc.id_chauffeur
         WHERE vc.id_voyage = vo.id_voyage) AS chauffeurs,
       (SELECT GROUP_CONCAT(es.nom_escale ORDER BY ve2.ordre_passage SEPARATOR ' > ')
          FROM voyage_escale ve2 JOIN escale es ON es.id_escale = ve2.id_escale
         WHERE ve2.id_voyage = vo.id_voyage) AS escales
  FROM voyage vo
  JOIN itineraire it ON it.id_itineraire = vo.id_itineraire
  JOIN vehicule ve   ON ve.immatriculation = vo.immatriculation;

-- Billet imprimable.
CREATE OR REPLACE VIEW v_billet_impression AS
SELECT b.id_billet, rv.nom_voyageur, rv.prenom_voyageur, b.numero_place, b.montant_fcfa,
       b.date_emission, vo.id_voyage, it.libelle AS itineraire, vo.date_voyage,
       vo.heure_depart_prevue, ve.immatriculation, p.moyen_paiement
  FROM billet b
  JOIN reservation_voyageur rv ON rv.id_reservation = b.id_reservation
                              AND rv.numero_voyageur = b.numero_voyageur
  JOIN voyage vo     ON vo.id_voyage = rv.id_voyage
  JOIN itineraire it ON it.id_itineraire = vo.id_itineraire
  JOIN vehicule ve   ON ve.immatriculation = vo.immatriculation
  JOIN paiement p    ON p.id_paiement = b.id_paiement;

-- Recette par session de caisse (controle de CI03).
CREATE OR REPLACE VIEW v_recette_session AS
SELECT s.id_session, a.nom_agence, CONCAT(e.prenom, ' ', e.nom) AS caissier, s.statut_session,
       s.date_heure_ouverture, s.date_heure_fermeture,
       COUNT(p.id_paiement)                       AS nb_paiements,
       COALESCE(SUM(p.montant_fcfa), 0)           AS total_calcule,
       s.total_encaisse_fcfa                      AS total_enregistre
  FROM session_de_caisse s
  JOIN agence a  ON a.id_agence = s.id_agence
  JOIN employe e ON e.id_employe = s.id_employe
  LEFT JOIN paiement p ON p.id_session = s.id_session AND p.statut_paiement = 'ENCAISSE'
 GROUP BY s.id_session, a.nom_agence, e.prenom, e.nom, s.statut_session,
          s.date_heure_ouverture, s.date_heure_fermeture, s.total_encaisse_fcfa;

-- Tableau de bord du chef d'agence : encaissements par agence et par jour.
CREATE OR REPLACE VIEW v_activite_agence_jour AS
SELECT a.id_agence, a.nom_agence, DATE(p.date_heure_paiement) AS jour,
       COUNT(*) AS nb_paiements, SUM(p.montant_fcfa) AS total_fcfa,
       SUM(COALESCE(p.montant_remise_fcfa, 0)) AS remises_fcfa
  FROM paiement p
  JOIN session_de_caisse s ON s.id_session = p.id_session
  JOIN agence a            ON a.id_agence = s.id_agence
 WHERE p.statut_paiement = 'ENCAISSE'
 GROUP BY a.id_agence, a.nom_agence, DATE(p.date_heure_paiement);

-- Suivi des colis.
CREATE OR REPLACE VIEW v_suivi_colis AS
SELECT c.id_colis, CONCAT(cl.prenom, ' ', cl.nom) AS expediteur, c.nom_destinataire,
       c.telephone_destinataire, c.statut_colis, c.date_heure_depot, c.date_heure_arrivee,
       c.date_heure_retrait, c.tarif_suggere_fcfa, c.montant_confirme_fcfa,
       (SELECT COUNT(*) FROM reclamation r WHERE r.id_colis = c.id_colis) AS nb_reclamations
  FROM colis c JOIN client cl ON cl.id_client = c.id_client_expediteur;

-- Employes et roles SANS le hash du mot de passe (a donner au compte d'affichage).
CREATE OR REPLACE VIEW v_employe_roles AS
SELECT e.id_employe, e.nom, e.prenom, e.email, e.id_agence, e.derniere_connexion,
       GROUP_CONCAT(r.libelle ORDER BY r.libelle SEPARATOR ', ') AS roles
  FROM employe e
  LEFT JOIN employe_role er ON er.id_employe = e.id_employe
  LEFT JOIN role r          ON r.id_role = er.id_role
 GROUP BY e.id_employe, e.nom, e.prenom, e.email, e.id_agence, e.derniere_connexion;

-- Controle CI02 : entreprises sans siege (doit etre vide).
CREATE OR REPLACE VIEW v_controle_sans_siege AS
SELECT en.id_entreprise, en.nom_commercial
  FROM entreprise en
 WHERE NOT EXISTS (SELECT 1 FROM agence a WHERE a.id_entreprise = en.id_entreprise AND a.est_siege = 1);
