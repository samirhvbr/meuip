-- MEUIP.RS — schema da tabela de visitas (auditoria/rastreabilidade)
--
-- Tabela lida pelo admin da INTRANET (conexão read-only "meuip").
-- Banco padrão: `mdata` (configurável em config.php). Nome separado do legado
-- `access_log` para não conflitar com o schema antigo.
--
--   mysql -h 100.64.66.211 -u <user> -p mdata < schema.sql

CREATE TABLE IF NOT EXISTS `meuip_visits` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip`          VARCHAR(45)     NOT NULL DEFAULT '',
    `method`      VARCHAR(10)     NOT NULL DEFAULT '',
    `scheme`      VARCHAR(10)     NOT NULL DEFAULT '',
    `host`        VARCHAR(191)    NOT NULL DEFAULT '',
    `request_uri` VARCHAR(512)    NOT NULL DEFAULT '',
    `referer`     VARCHAR(512)    NOT NULL DEFAULT '',
    `user_agent`  VARCHAR(512)    NOT NULL DEFAULT '',
    `os`          VARCHAR(64)     NOT NULL DEFAULT '',
    `browser`     VARCHAR(64)     NOT NULL DEFAULT '',
    `asn`         VARCHAR(32)     NOT NULL DEFAULT '',
    `isp`         VARCHAR(191)    NOT NULL DEFAULT '',
    `country`     VARCHAR(8)      NOT NULL DEFAULT '',
    `region`      VARCHAR(128)    NOT NULL DEFAULT '',
    `city`        VARCHAR(128)    NOT NULL DEFAULT '',
    `hostname`    VARCHAR(255)    NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_ip` (`ip`),
    KEY `idx_country` (`country`),
    KEY `idx_asn` (`asn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
