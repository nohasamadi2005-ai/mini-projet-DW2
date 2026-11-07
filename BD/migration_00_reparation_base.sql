-- =====================================================================
--  MIGRATION 00 : remise à niveau de la base (à exécuter AVANT la 01)
--  Le code de l'équipe utilise des colonnes absentes de la base partagée.
--  Sans effet si elles existent déjà (IF NOT EXISTS). Compatible MariaDB 10.4+.
-- =====================================================================

-- Réclamations : sous-type, lieu de la fuite et photo (BD/reclamation.php),
-- et réclamation possible sans facture (fuite externe, déménagement…)
ALTER TABLE `reclamation`
  ADD COLUMN IF NOT EXISTS `Sous_Type`  varchar(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Lieu_Fuite` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Photo`      varchar(255) DEFAULT NULL,
  MODIFY `ID_Facture` int(11) DEFAULT NULL;

-- Factures : TVA était limitée à 999,99 DH (decimal(5,2)). Une facture de commerce ou
-- d'usine (zones commerciales / industrielles) dépasse ce montant et faisait planter l'insertion.
ALTER TABLE `facture` MODIFY `TVA` decimal(12,2) NOT NULL DEFAULT 0.00;
