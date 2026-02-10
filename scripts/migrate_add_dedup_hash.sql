-- Ajout du hash de deduplication pour les recettes
-- NOTE: l'index unique autorise plusieurs NULL, mais refusera les doublons une fois le champ rempli.

ALTER TABLE recettes
    ADD COLUMN dedup_hash CHAR(64) DEFAULT NULL;

CREATE UNIQUE INDEX uniq_recettes_dedup_hash ON recettes (dedup_hash);
