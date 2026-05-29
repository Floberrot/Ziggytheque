-- ============================================================================
-- Ziggytheque — anonymisation RGPD de la base STAGING
-- ============================================================================
-- À exécuter UNE FOIS, juste après avoir restauré un dump de PROD sur la base
-- de l'environnement STAGING. Scrub toutes les données personnelles : e-mails,
-- noms affichés, e-mails de notification, webhooks Discord, jetons d'auth, et
-- le journal d'activité (dont la metadata peut contenir des traces PII).
--
-- ⚠️  NE JAMAIS exécuter sur la base de PRODUCTION.
--     Vérifie deux fois la connexion (host/db) avant de lancer.
--
-- Usage typique (depuis une machine de confiance, connectée à la base staging) :
--   psql "$STAGING_DATABASE_URL" -f back/scripts/anonymize-staging.sql
--
-- Les données métier (mangas, volumes, collections, wishlist, articles) ne
-- contiennent pas de PII et sont volontairement conservées telles quelles —
-- c'est ce qu'on veut tester en staging.
-- ============================================================================

BEGIN;

-- 1) Utilisateurs : on garde la structure (rôles, statuts, canal de notif) mais
--    on neutralise toute donnée personnelle.
--    - email : déterministe et UNIQUE (basé sur l'id), domaine .invalid non routable
--    - password_hash : hash bcrypt volontairement INUTILISABLE (aucun mot de
--      passe ne peut s'y vérifier). Crée ensuite un admin de staging via
--      `php bin/console app:bootstrap-admin …` pour pouvoir te connecter.
UPDATE users SET
    email               = 'user-' || id || '@staging.invalid',
    display_name        = 'Staging User ' || substr(id, 1, 8),
    notification_email  = NULL,
    discord_webhook_url = NULL,
    password_hash       = '$2y$13$00000000000000000000000000000000000000000000000000000'
;

-- 2) Jetons d'authentification (reset de mot de passe, vérification e-mail) :
--    on ne conserve JAMAIS de jetons réels en staging.
DELETE FROM auth_tokens;

-- 3) Journal d'activité : sa metadata (JSON) et ses messages d'erreur peuvent
--    contenir des e-mails / URLs de webhook → purge complète de l'historique.
DELETE FROM activity_logs;

COMMIT;

-- ── Vérification post-anonymisation (doit ne montrer que des @staging.invalid) :
--   SELECT email, display_name, notification_email, discord_webhook_url
--   FROM users ORDER BY created_at LIMIT 10;
--   SELECT count(*) AS auth_tokens_restants FROM auth_tokens;   -- attendu : 0
--   SELECT count(*) AS activity_logs_restants FROM activity_logs; -- attendu : 0
