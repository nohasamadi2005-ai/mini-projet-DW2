-- =====================================================================
--  MIGRATION 01 : CONTRATS + ZONES (quartiers)            Personne 1
--  À exécuter UNE SEULE FOIS dans phpMyAdmin (onglet SQL), sur la base
--  utilisée par l'équipe. Faites un export (sauvegarde) avant.
--  Compatible MariaDB 10.4 (XAMPP) et plus récent.
--
--  Principe :
--   - Un CONTRAT relie un client à un compteur, dans un quartier (zone),
--     avec une date de début, et à la résiliation une date et un index de fin.
--   - Le compteur ne bouge jamais (il reste dans le logement / le local) :
--     tous les contrats successifs d'un compteur sont dans le même quartier.
--   - Un quartier a UN type d'activité (règle du prof) : une zone industrielle
--     ne contient que des industries, une zone résidentielle que des logements,
--     une zone commerciale que des commerces. Le type du contrat est donc
--     celui de son quartier.
--   - compteur.ID_Client est CONSERVÉ : c'est le titulaire du contrat actif
--     (NULL si le compteur est libre). Tout le code existant continue de
--     marcher. Il est tenu à jour par BD/Contrat.php.
--   - La table historique_compteur (si elle existe chez vous) est remplacée
--     par contrat : ses lignes sont reprises, puis elle est renommée
--     historique_compteur_ancien (rien n'est supprimé).
--
--  Personne 2 ajoutera ensuite les tarifs (ALTER TABLE type_activite +
--  table tarif) sans rien changer à ce qui est créé ici.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Types d'activité d'une zone
-- ---------------------------------------------------------------------
CREATE TABLE `type_activite` (
  `ID_Type_Activite` int(11)      NOT NULL AUTO_INCREMENT,
  `Libelle`          varchar(50)  NOT NULL,
  `Description`      varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID_Type_Activite`),
  UNIQUE KEY `uq_type_activite_libelle` (`Libelle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `type_activite` (`ID_Type_Activite`, `Libelle`, `Description`) VALUES
(1, 'Résidentiel', 'Zone de logements : uniquement des résidences'),
(2, 'Commercial',  'Zone commerciale : uniquement des commerces et bureaux'),
(3, 'Industriel',  'Zone industrielle : uniquement des usines et ateliers');


-- ---------------------------------------------------------------------
-- 2. Quartiers (zones). Chaque quartier a un seul type d'activité.
-- ---------------------------------------------------------------------
CREATE TABLE `quartier` (
  `ID_Quartier`      int(11)      NOT NULL AUTO_INCREMENT,
  `Nom`              varchar(100) NOT NULL,
  `Ville`            varchar(100) NOT NULL,
  `ID_Type_Activite` int(11)      NOT NULL,
  PRIMARY KEY (`ID_Quartier`),
  UNIQUE KEY `uq_quartier_nom_ville` (`Nom`, `Ville`),
  KEY `fk_quartier_type` (`ID_Type_Activite`),
  CONSTRAINT `fk_quartier_type` FOREIGN KEY (`ID_Type_Activite`)
      REFERENCES `type_activite` (`ID_Type_Activite`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `quartier` (`ID_Quartier`, `Nom`, `Ville`, `ID_Type_Activite`) VALUES
(1, 'Al Manar',          'Larache', 1),
(2, 'Hay Jadid',         'Larache', 1),
(3, 'Médina - Centre',   'Larache', 2),
(4, 'Zone industrielle', 'Larache', 3),
(5, 'Mhannech',          'Tétouan', 1),
(6, 'Touabel',           'Tétouan', 1),
(7, 'Centre-ville',      'Tétouan', 2),
(8, 'Zone industrielle', 'Tétouan', 3);


-- ---------------------------------------------------------------------
-- 3. Contrats
-- ---------------------------------------------------------------------
CREATE TABLE `contrat` (
  `ID_Contrat`          int(11)       NOT NULL AUTO_INCREMENT,
  `ID_Client`           int(11)       NOT NULL,
  `ID_Compteur`         int(11)       NOT NULL,
  `ID_Quartier`         int(11)       NOT NULL,
  `Adresse_Branchement` varchar(255)  DEFAULT NULL,      -- adresse exacte du logement / local
  `Date_Debut`          date          NOT NULL,
  `Date_Fin`            date          DEFAULT NULL,      -- NULL tant que le contrat est actif
  `Index_Debut`         decimal(12,2) NOT NULL DEFAULT 0.00,
  `Index_Fin`           decimal(12,2) DEFAULT NULL,      -- index de clôture
  `Statut`              enum('Actif','Resilie') NOT NULL DEFAULT 'Actif',
  `Motif_Fin`           enum('Resiliation','Demenagement','Transfert') DEFAULT NULL,
  `ID_Admin`            int(11)       DEFAULT NULL,      -- fournisseur qui a créé le contrat
  `Date_Creation`       datetime      DEFAULT current_timestamp(),
  -- Vaut ID_Compteur si le contrat est actif, sinon NULL. L'index UNIQUE dessus
  -- interdit 2 contrats actifs sur le même compteur (les NULL ne comptent pas).
  `Compteur_Actif`      int(11) GENERATED ALWAYS AS (IF(`Statut` = 'Actif', `ID_Compteur`, NULL)) STORED,
  PRIMARY KEY (`ID_Contrat`),
  UNIQUE KEY `uq_contrat_compteur_actif` (`Compteur_Actif`),
  KEY `fk_contrat_client`   (`ID_Client`),
  KEY `fk_contrat_compteur` (`ID_Compteur`),
  KEY `fk_contrat_quartier` (`ID_Quartier`),
  KEY `fk_contrat_admin`    (`ID_Admin`),
  KEY `idx_contrat_statut`  (`Statut`),
  CONSTRAINT `fk_contrat_client`   FOREIGN KEY (`ID_Client`)   REFERENCES `client`   (`ID_Client`)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contrat_compteur` FOREIGN KEY (`ID_Compteur`) REFERENCES `compteur` (`ID_Compteur`),
  CONSTRAINT `fk_contrat_quartier` FOREIGN KEY (`ID_Quartier`) REFERENCES `quartier` (`ID_Quartier`) ON UPDATE CASCADE,
  CONSTRAINT `fk_contrat_admin`    FOREIGN KEY (`ID_Admin`)    REFERENCES `admin`    (`ID_Admin`)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- AUTO_INCREMENT=1001 : un n° de contrat (1001, 1002…) ne se confond pas avec un n° de compteur.


-- ---------------------------------------------------------------------
-- 4. Reprise des données existantes
-- ---------------------------------------------------------------------
-- 4a. La table historique_compteur n'existe pas dans toutes les bases de l'équipe :
--     on la crée vide si besoin, pour que la reprise fonctionne partout.
CREATE TABLE IF NOT EXISTS `historique_compteur` (
  `ID_Historique` int(11)       NOT NULL AUTO_INCREMENT,
  `ID_Compteur`   int(11)       NOT NULL,
  `ID_Client`     int(11)       DEFAULT NULL,
  `Date_Debut`    datetime      NOT NULL,
  `Date_Fin`      datetime      DEFAULT NULL,
  `Index_Debut`   decimal(12,2) DEFAULT NULL,
  `Index_Fin`     decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`ID_Historique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Zone par défaut d'un contrat repris : quartier résidentiel de la ville du client
-- (Tétouan -> Mhannech, sinon Al Manar). À corriger ensuite si besoin dans l'écran Contrats
-- (bouton « Zone »), tant que le compteur n'a qu'un contrat.

-- 4b. Anciennes affectations terminées -> contrats résiliés
INSERT INTO `contrat` (`ID_Client`, `ID_Compteur`, `ID_Quartier`, `Adresse_Branchement`, `Date_Debut`, `Date_Fin`,
                       `Index_Debut`, `Index_Fin`, `Statut`, `Motif_Fin`, `Date_Creation`)
SELECT h.`ID_Client`, h.`ID_Compteur`,
       IF(LOWER(cl.`Adresse`) LIKE '%t_touan%', 5, 1),
       cl.`Adresse`, DATE(h.`Date_Debut`), DATE(h.`Date_Fin`),
       COALESCE(h.`Index_Debut`, cp.`Index_Initial`), COALESCE(h.`Index_Fin`, h.`Index_Debut`, cp.`Index_Initial`),
       'Resilie', 'Resiliation', h.`Date_Debut`
  FROM `historique_compteur` h
  JOIN `client`   cl ON cl.`ID_Client`   = h.`ID_Client`
  JOIN `compteur` cp ON cp.`ID_Compteur` = h.`ID_Compteur`
 WHERE h.`Date_Fin` IS NOT NULL
 ORDER BY h.`Date_Debut`, h.`ID_Historique`;

-- 4c. Compteurs actuellement affectés -> un contrat actif chacun
--     (date et index de début repris de l'historique ouvert s'il existe)
INSERT INTO `contrat` (`ID_Client`, `ID_Compteur`, `ID_Quartier`, `Adresse_Branchement`, `Date_Debut`,
                       `Index_Debut`, `Statut`, `Date_Creation`)
SELECT cp.`ID_Client`, cp.`ID_Compteur`,
       IF(LOWER(cl.`Adresse`) LIKE '%t_touan%', 5, 1),
       cl.`Adresse`,
       COALESCE((SELECT DATE(MAX(h.`Date_Debut`)) FROM `historique_compteur` h
                  WHERE h.`ID_Compteur` = cp.`ID_Compteur` AND h.`ID_Client` = cp.`ID_Client` AND h.`Date_Fin` IS NULL),
                cp.`Date_Installation`, CURDATE()),
       COALESCE((SELECT h.`Index_Debut` FROM `historique_compteur` h
                  WHERE h.`ID_Compteur` = cp.`ID_Compteur` AND h.`ID_Client` = cp.`ID_Client` AND h.`Date_Fin` IS NULL
                  ORDER BY h.`Date_Debut` DESC LIMIT 1),
                cp.`Index_Initial`),
       'Actif', NOW()
  FROM `compteur` cp
  JOIN `client` cl ON cl.`ID_Client` = cp.`ID_Client`
 ORDER BY cp.`ID_Client`, cp.`ID_Compteur`;

-- 4d. Un compteur garde la même zone pour tous ses contrats (il ne bouge pas)
UPDATE `contrat` c
  JOIN (SELECT `ID_Compteur`, MIN(`ID_Quartier`) AS q FROM `contrat` WHERE `Statut` = 'Actif' GROUP BY `ID_Compteur`) a
    ON a.`ID_Compteur` = c.`ID_Compteur`
   SET c.`ID_Quartier` = a.q;

-- 4e. L'ancienne table n'est plus utilisée par le code : on la garde sous un autre nom
RENAME TABLE `historique_compteur` TO `historique_compteur_ancien`;

-- 4f. Deux orthographes du statut compteur ('active' / 'actif') : on garde 'actif' / 'inactif'
UPDATE `compteur` SET `statut` = 'actif'   WHERE `statut` = 'active';
UPDATE `compteur` SET `statut` = 'inactif' WHERE `ID_Client` IS NULL;
UPDATE `compteur` SET `statut` = 'actif'   WHERE `ID_Client` IS NOT NULL;
ALTER TABLE `compteur` MODIFY `statut` varchar(20) NOT NULL DEFAULT 'inactif';


-- ---------------------------------------------------------------------
-- 5. Le relevé de clôture d'un contrat est enregistré comme un relevé
--    mensuel vérifié : cette valeur d'ENUM est déjà utilisée par le code
--    (BD/Anomalie.php, traitement/Saisie.php) mais manquait dans la base.
-- ---------------------------------------------------------------------
ALTER TABLE `consommation_mensuelle`
  MODIFY `Statut_Traitement`
         enum('En attente','Verifie','Rejete','Anomalie','Resaisie demandee','Saisie_Fournisseur')
         DEFAULT 'En attente';
