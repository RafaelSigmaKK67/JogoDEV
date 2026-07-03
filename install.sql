-- ============================================================
-- DEV SURVIVOR - Banco de Dados Completo (v2 - EXPANSAO)
-- Jogo battle royale 2D com tema de programacao
--
-- COMO IMPORTAR:
--   1. Abra http://localhost/phpmyadmin
--   2. Clique na aba "Importar"
--   3. Selecione este arquivo (install.sql) e clique em "Executar"
--      (ou cole todo o conteudo na aba "SQL" e execute)
--
-- Usuario admin padrao:
--   E-mail: admin@devsurvivor.com
--   Senha : admin123
--
-- OBS: este script RECRIA o banco do zero (apaga dados antigos).
-- ============================================================

CREATE DATABASE IF NOT EXISTS dev_survivor
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dev_survivor;

-- Remove tabelas antigas na ordem correta de dependencia (FK)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS sandbox_matches;
DROP TABLE IF EXISTS player_equipment;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS player_account_rewards;
DROP TABLE IF EXISTS account_level_rewards;
DROP TABLE IF EXISTS roulette_history;
DROP TABLE IF EXISTS roulette_rewards;
DROP TABLE IF EXISTS map_completions;
DROP TABLE IF EXISTS player_medals;
DROP TABLE IF EXISTS medals;
DROP TABLE IF EXISTS player_loadout;
DROP TABLE IF EXISTS player_effects;
DROP TABLE IF EXISTS player_weapon_skins;
DROP TABLE IF EXISTS player_skins;
DROP TABLE IF EXISTS player_items;
DROP TABLE IF EXISTS store_items;
DROP TABLE IF EXISTS player_characters;
DROP TABLE IF EXISTS characters;
DROP TABLE IF EXISTS bosses;
DROP TABLE IF EXISTS difficulty_modes;
DROP TABLE IF EXISTS admin_logs;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS rankings;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS players_stats;
DROP TABLE IF EXISTS weapons;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS enemies;
DROP TABLE IF EXISTS maps;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. USUARIOS
-- ============================================================
CREATE TABLE users (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(60)  NOT NULL,
  email      VARCHAR(120) NOT NULL,
  password   VARCHAR(255) NOT NULL,
  is_admin   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. PROGRESSO DO JOGADOR
-- ============================================================
CREATE TABLE players_stats (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id             INT UNSIGNED NOT NULL,
  level               INT UNSIGNED NOT NULL DEFAULT 1,
  xp                  INT UNSIGNED NOT NULL DEFAULT 0,
  coins               INT UNSIGNED NOT NULL DEFAULT 0,
  total_score         BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_kills         INT UNSIGNED NOT NULL DEFAULT 0,
  total_matches       INT UNSIGNED NOT NULL DEFAULT 0,
  total_survival_time INT UNSIGNED NOT NULL DEFAULT 0,
  best_score          INT UNSIGNED NOT NULL DEFAULT 0,
  best_kills          INT UNSIGNED NOT NULL DEFAULT 0,
  best_survival_time  INT UNSIGNED NOT NULL DEFAULT 0,
  maps_completed      INT UNSIGNED NOT NULL DEFAULT 0,
  last_free_roulette  DATE NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stats_user (user_id),
  CONSTRAINT fk_stats_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. DIFICULDADES (6 modos, multiplicadores aplicados no jogo)
-- ============================================================
CREATE TABLE difficulty_modes (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(40)  NOT NULL,
  slug             VARCHAR(40)  NOT NULL,
  order_index      INT UNSIGNED NOT NULL DEFAULT 0,
  hp_mult          FLOAT NOT NULL DEFAULT 1.0,   -- vida dos inimigos
  damage_mult      FLOAT NOT NULL DEFAULT 1.0,   -- dano dos inimigos
  speed_mult       FLOAT NOT NULL DEFAULT 1.0,   -- velocidade dos inimigos
  count_mult       FLOAT NOT NULL DEFAULT 1.0,   -- quantidade de inimigos
  time_required    INT UNSIGNED NOT NULL DEFAULT 150, -- segundos para o boss aparecer
  coin_mult        FLOAT NOT NULL DEFAULT 1.0,   -- moedas recebidas
  xp_mult          FLOAT NOT NULL DEFAULT 1.0,   -- XP recebido
  rare_drop_chance FLOAT NOT NULL DEFAULT 0.05,  -- chance de item raro
  color            VARCHAR(20) NOT NULL DEFAULT '#4df3a3',
  PRIMARY KEY (id),
  UNIQUE KEY uq_diff_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. MAPAS (10 mapas)
-- ============================================================
CREATE TABLE maps (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(80)  NOT NULL,
  slug             VARCHAR(80)  NOT NULL,
  description      TEXT         NULL,
  theme            VARCHAR(120) NULL,
  difficulty       VARCHAR(20)  NOT NULL DEFAULT 'facil', -- tier base (so para exibir)
  main_enemies     VARCHAR(160) NULL,
  boss_id          INT UNSIGNED NULL,
  width            INT UNSIGNED NOT NULL DEFAULT 2000,
  height           INT UNSIGNED NOT NULL DEFAULT 2000,
  bg_color         VARCHAR(20)  NOT NULL DEFAULT '#060a12',
  accent_color     VARCHAR(20)  NOT NULL DEFAULT '#1f6f4a',
  obstacle_style   VARCHAR(30)  NOT NULL DEFAULT 'blocks',
  obstacle_count   INT UNSIGNED NOT NULL DEFAULT 18,
  enemy_multiplier FLOAT        NOT NULL DEFAULT 1.0,
  spawn_rate       FLOAT        NOT NULL DEFAULT 1.0,
  has_hazards      TINYINT(1)   NOT NULL DEFAULT 0,
  reward_coins     INT UNSIGNED NOT NULL DEFAULT 100, -- recompensa base por concluir
  reward_xp        INT UNSIGNED NOT NULL DEFAULT 200,
  unlock_level     INT UNSIGNED NOT NULL DEFAULT 1,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_maps_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. BOSSES (10, um por mapa)
-- ============================================================
CREATE TABLE bosses (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(80)  NOT NULL,
  slug          VARCHAR(80)  NOT NULL,
  description   TEXT         NULL,
  map_id        INT UNSIGNED NULL,
  health        INT UNSIGNED NOT NULL DEFAULT 1500,
  speed         FLOAT        NOT NULL DEFAULT 60,
  damage        INT UNSIGNED NOT NULL DEFAULT 30,
  size          INT UNSIGNED NOT NULL DEFAULT 46,
  color         VARCHAR(20)  NOT NULL DEFAULT '#ff0044',
  special_attack VARCHAR(30) NOT NULL DEFAULT 'radial', -- radial|summon|charge|spread|laser|nova
  xp_reward     INT UNSIGNED NOT NULL DEFAULT 400,
  coin_reward   INT UNSIGNED NOT NULL DEFAULT 80,
  score_reward  INT UNSIGNED NOT NULL DEFAULT 1000,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bosses_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. INIMIGOS COMUNS (anomalias digitais)
-- ============================================================
CREATE TABLE enemies (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)  NOT NULL,
  slug         VARCHAR(80)  NOT NULL,
  description  TEXT         NULL,
  health       INT UNSIGNED NOT NULL DEFAULT 20,
  speed        FLOAT        NOT NULL DEFAULT 100,
  damage       INT UNSIGNED NOT NULL DEFAULT 8,
  xp_reward    INT UNSIGNED NOT NULL DEFAULT 5,
  coin_reward  INT UNSIGNED NOT NULL DEFAULT 1,
  score_reward INT UNSIGNED NOT NULL DEFAULT 10,
  size         INT UNSIGNED NOT NULL DEFAULT 14,
  color        VARCHAR(20)  NOT NULL DEFAULT '#ff4d5e',
  behavior     VARCHAR(30)  NOT NULL DEFAULT 'chase',
  spawn_weight INT UNSIGNED NOT NULL DEFAULT 10,
  is_boss      TINYINT(1)   NOT NULL DEFAULT 0,
  min_time     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enemies_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. ARMAS
-- ============================================================
CREATE TABLE weapons (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)  NOT NULL,
  slug         VARCHAR(80)  NOT NULL,
  description  TEXT         NULL,
  damage       INT UNSIGNED NOT NULL DEFAULT 10,
  fire_rate    FLOAT        NOT NULL DEFAULT 2.0,
  bullet_speed INT UNSIGNED NOT NULL DEFAULT 420,
  bullet_range INT UNSIGNED NOT NULL DEFAULT 400,
  projectiles  INT UNSIGNED NOT NULL DEFAULT 1,
  spread       FLOAT        NOT NULL DEFAULT 0,
  special      VARCHAR(30)  NULL,
  color        VARCHAR(20)  NOT NULL DEFAULT '#4df3a3',
  unlock_level INT UNSIGNED NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_weapons_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. HABILIDADES (escolhidas ao subir de nivel na partida)
-- ============================================================
CREATE TABLE skills (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)  NOT NULL,
  slug         VARCHAR(80)  NOT NULL,
  description  TEXT         NULL,
  effect       VARCHAR(30)  NOT NULL,
  effect_value FLOAT        NOT NULL DEFAULT 0,
  max_stacks   INT UNSIGNED NOT NULL DEFAULT 5,
  icon         VARCHAR(10)  NOT NULL DEFAULT '*',
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skills_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. PERSONAGENS JOGAVEIS (sobreviventes)
-- ============================================================
CREATE TABLE characters (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(80)  NOT NULL,
  slug           VARCHAR(80)  NOT NULL,
  description    TEXT         NULL,
  base_health    INT UNSIGNED NOT NULL DEFAULT 100,
  base_speed     FLOAT        NOT NULL DEFAULT 210,
  base_damage    FLOAT        NOT NULL DEFAULT 1.0,   -- multiplicador de dano
  base_defense   FLOAT        NOT NULL DEFAULT 0,     -- 0..0.6 reducao de dano
  passive_key    VARCHAR(30)  NOT NULL DEFAULT 'none',
  passive_value  FLOAT        NOT NULL DEFAULT 0,
  passive_desc   VARCHAR(160) NULL,
  special_name   VARCHAR(80)  NULL,
  special_key    VARCHAR(30)  NOT NULL DEFAULT 'none',
  special_desc   VARCHAR(160) NULL,
  ultimate_name  VARCHAR(80)  NULL,
  ultimate_key   VARCHAR(30)  NOT NULL DEFAULT 'none',
  ultimate_desc  VARCHAR(160) NULL,
  unlock_type    VARCHAR(20)  NOT NULL DEFAULT 'coins', -- default|level|coins
  unlock_value   INT UNSIGNED NOT NULL DEFAULT 0,
  color          VARCHAR(20)  NOT NULL DEFAULT '#4df3a3',
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_characters_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personagens que cada jogador possui
CREATE TABLE player_characters (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  character_id INT UNSIGNED NOT NULL,
  acquired_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pc (user_id, character_id),
  CONSTRAINT fk_pc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_char FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. LOJA (itens cosmeticos e funcionais)
-- ============================================================
CREATE TABLE store_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(80)  NOT NULL,
  slug          VARCHAR(80)  NOT NULL,
  description   TEXT         NULL,
  category      VARCHAR(30)  NOT NULL, -- character_skin|weapon_skin|char_effect|shot_effect|kill_effect|entrance|frame|coin_pack|roulette
  rarity        VARCHAR(20)  NOT NULL DEFAULT 'comum', -- comum|incomum|raro|epico|lendario|mitico
  price         INT UNSIGNED NOT NULL DEFAULT 100,
  effect_key    VARCHAR(30)  NULL,  -- efeito no jogo (quando aplicavel)
  effect_value  FLOAT        NOT NULL DEFAULT 0,
  color         VARCHAR(20)  NOT NULL DEFAULT '#4df3a3',
  payload       INT          NOT NULL DEFAULT 0, -- ex: qtd de moedas do coin_pack / ref da roleta
  character_slug VARCHAR(80) NULL, -- (opcional) skin ligada a um personagem
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  unlock_level  INT UNSIGNED NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens da loja que o jogador possui (tabela unificada de propriedade)
CREATE TABLE player_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  store_item_id INT UNSIGNED NOT NULL,
  acquired_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pi (user_id, store_item_id),
  CONSTRAINT fk_pi_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_item FOREIGN KEY (store_item_id) REFERENCES store_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelas tipadas (espelho por categoria, populadas por grantStoreItem())
CREATE TABLE player_skins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  store_item_id INT UNSIGNED NOT NULL,
  acquired_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ps (user_id, store_item_id),
  CONSTRAINT fk_ps_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_item FOREIGN KEY (store_item_id) REFERENCES store_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_weapon_skins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  store_item_id INT UNSIGNED NOT NULL,
  acquired_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pws (user_id, store_item_id),
  CONSTRAINT fk_pws_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pws_item FOREIGN KEY (store_item_id) REFERENCES store_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_effects (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  store_item_id INT UNSIGNED NOT NULL,
  acquired_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pe (user_id, store_item_id),
  CONSTRAINT fk_pe_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_item FOREIGN KEY (store_item_id) REFERENCES store_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. EQUIPAMENTOS (gear funcional com bonus)
-- ============================================================
CREATE TABLE equipment (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)  NOT NULL,
  slug         VARCHAR(80)  NOT NULL,
  description  TEXT         NULL,
  slot         VARCHAR(20)  NOT NULL, -- helmet|armor|gloves|boots|chip|amulet
  rarity       VARCHAR(20)  NOT NULL DEFAULT 'comum',
  price        INT UNSIGNED NOT NULL DEFAULT 200,
  bonus_key    VARCHAR(30)  NOT NULL DEFAULT 'max_health', -- max_health|damage|defense|speed|xp|coins|cooldown
  bonus_value  FLOAT        NOT NULL DEFAULT 0,
  color        VARCHAR(20)  NOT NULL DEFAULT '#4df3a3',
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  unlock_level INT UNSIGNED NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_equip_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_equipment (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NOT NULL,
  acquired_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_peq (user_id, equipment_id),
  CONSTRAINT fk_peq_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_peq_item FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. LOADOUT (configuracao equipada do jogador, 1 linha por user)
-- ============================================================
CREATE TABLE player_loadout (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id               INT UNSIGNED NOT NULL,
  character_id          INT UNSIGNED NULL,
  character_skin_item_id INT UNSIGNED NULL,
  weapon_skin_item_id   INT UNSIGNED NULL,
  char_effect_item_id   INT UNSIGNED NULL,
  shot_effect_item_id   INT UNSIGNED NULL,
  kill_effect_item_id   INT UNSIGNED NULL,
  entrance_item_id      INT UNSIGNED NULL,
  frame_item_id         INT UNSIGNED NULL,
  equip_helmet_id       INT UNSIGNED NULL,
  equip_armor_id        INT UNSIGNED NULL,
  equip_gloves_id       INT UNSIGNED NULL,
  equip_boots_id        INT UNSIGNED NULL,
  equip_chip_id         INT UNSIGNED NULL,
  equip_amulet_id       INT UNSIGNED NULL,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loadout_user (user_id),
  CONSTRAINT fk_loadout_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. MEDALHAS
-- ============================================================
CREATE TABLE medals (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  slug          VARCHAR(120) NOT NULL,
  kind          VARCHAR(20)  NOT NULL, -- map_diff|map_master|supreme
  map_id        INT UNSIGNED NULL,
  difficulty_id INT UNSIGNED NULL,
  icon          VARCHAR(10)  NOT NULL DEFAULT '🏅',
  description   VARCHAR(200) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_medal_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_medals (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id   INT UNSIGNED NOT NULL,
  medal_id  INT UNSIGNED NOT NULL,
  earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pm (user_id, medal_id),
  CONSTRAINT fk_pm_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
  CONSTRAINT fk_pm_medal FOREIGN KEY (medal_id) REFERENCES medals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. CONCLUSOES DE MAPA
-- ============================================================
CREATE TABLE map_completions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  map_id        INT UNSIGNED NOT NULL,
  difficulty_id INT UNSIGNED NOT NULL,
  survival_time INT UNSIGNED NOT NULL DEFAULT 0,
  score         INT UNSIGNED NOT NULL DEFAULT 0,
  kills         INT UNSIGNED NOT NULL DEFAULT 0,
  boss_defeated TINYINT(1)   NOT NULL DEFAULT 1,
  medal_id      INT UNSIGNED NULL,
  reward_coins  INT UNSIGNED NOT NULL DEFAULT 0,
  reward_xp     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mc (user_id, map_id, difficulty_id),
  CONSTRAINT fk_mc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_mc_map  FOREIGN KEY (map_id)  REFERENCES maps (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. PARTIDAS (oficiais)
-- ============================================================
CREATE TABLE matches (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  map_id        INT UNSIGNED NULL,
  difficulty_id INT UNSIGNED NULL,
  character_id  INT UNSIGNED NULL,
  score         INT UNSIGNED NOT NULL DEFAULT 0,
  kills         INT UNSIGNED NOT NULL DEFAULT 0,
  survival_time INT UNSIGNED NOT NULL DEFAULT 0,
  xp_earned     INT UNSIGNED NOT NULL DEFAULT 0,
  coins_earned  INT UNSIGNED NOT NULL DEFAULT 0,
  level_reached INT UNSIGNED NOT NULL DEFAULT 1,
  completed     TINYINT(1)   NOT NULL DEFAULT 0,  -- boss derrotado / mapa concluido
  died_to       VARCHAR(80)  NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_matches_user (user_id),
  KEY idx_matches_score (score),
  CONSTRAINT fk_matches_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_map  FOREIGN KEY (map_id)  REFERENCES maps (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. PARTIDAS SANDBOX (separadas, nao contam no ranking)
-- ============================================================
CREATE TABLE sandbox_matches (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  map_id        INT UNSIGNED NULL,
  difficulty_id INT UNSIGNED NULL,
  character_id  INT UNSIGNED NULL,
  score         INT UNSIGNED NOT NULL DEFAULT 0,
  kills         INT UNSIGNED NOT NULL DEFAULT 0,
  survival_time INT UNSIGNED NOT NULL DEFAULT 0,
  settings_json TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sb_user (user_id),
  CONSTRAINT fk_sb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. RANKING
-- ============================================================
CREATE TABLE rankings (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            INT UNSIGNED NOT NULL,
  best_score         INT UNSIGNED NOT NULL DEFAULT 0,
  best_kills         INT UNSIGNED NOT NULL DEFAULT 0,
  best_survival_time INT UNSIGNED NOT NULL DEFAULT 0,
  total_score        BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_kills        INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rankings_user (user_id),
  KEY idx_rankings_best (best_score),
  CONSTRAINT fk_rankings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. INVENTARIO LEGADO (armas coletadas na partida)
-- ============================================================
CREATE TABLE inventory (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  item_type  VARCHAR(20)  NOT NULL DEFAULT 'weapon',
  item_id    INT UNSIGNED NOT NULL,
  quantity   INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_item (user_id, item_type, item_id),
  CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. ROLETAS
-- ============================================================
CREATE TABLE roulette_rewards (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  roulette_type VARCHAR(20) NOT NULL, -- free|common|rare|legendary
  reward_type   VARCHAR(20) NOT NULL, -- coins|xp|skin|weapon_skin|effect|weapon|equipment|character|item
  reward_slug   VARCHAR(80) NULL,     -- slug do item concedido (quando aplicavel)
  reward_amount INT NOT NULL DEFAULT 0,
  label         VARCHAR(120) NOT NULL,
  rarity        VARCHAR(20) NOT NULL DEFAULT 'comum',
  weight        INT UNSIGNED NOT NULL DEFAULT 10, -- peso no sorteio
  color         VARCHAR(20) NOT NULL DEFAULT '#4df3a3',
  PRIMARY KEY (id),
  KEY idx_rr_type (roulette_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roulette_history (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  roulette_type VARCHAR(20) NOT NULL,
  reward_type   VARCHAR(20) NOT NULL,
  label         VARCHAR(120) NOT NULL,
  rarity        VARCHAR(20) NOT NULL DEFAULT 'comum',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rh_user (user_id),
  CONSTRAINT fk_rh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. RECOMPENSAS POR NIVEL DE CONTA
-- ============================================================
CREATE TABLE account_level_rewards (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  level         INT UNSIGNED NOT NULL,
  reward_type   VARCHAR(20) NOT NULL, -- coins|xp|skin|weapon_skin|effect|weapon|equipment|character|roulette
  reward_slug   VARCHAR(80) NULL,
  reward_amount INT NOT NULL DEFAULT 0,
  label         VARCHAR(120) NOT NULL,
  icon          VARCHAR(10) NOT NULL DEFAULT '🎁',
  PRIMARY KEY (id),
  UNIQUE KEY uq_alr_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_account_rewards (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  reward_id  INT UNSIGNED NOT NULL,
  claimed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_par (user_id, reward_id),
  CONSTRAINT fk_par_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_par_reward FOREIGN KEY (reward_id) REFERENCES account_level_rewards (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 21. LOG ADMIN
-- ============================================================
CREATE TABLE admin_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60)  NOT NULL,
  details    TEXT         NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ============================================================
--                       DADOS INICIAIS
-- ============================================================
-- ============================================================

-- ---------- Admin padrao (senha: admin123) ----------
INSERT INTO users (name, email, password, is_admin) VALUES
('Admin', 'admin@devsurvivor.com', '$2y$10$MDSjJjBBLEiSD.HoMnE8.eSc7yARpPrXbKACyo55EiCI6w2ExZdxe', 1);
INSERT INTO players_stats (user_id, coins) VALUES (1, 5000);
INSERT INTO rankings (user_id) VALUES (1);

-- ---------- DIFICULDADES ----------
INSERT INTO difficulty_modes (name, slug, order_index, hp_mult, damage_mult, speed_mult, count_mult, time_required, coin_mult, xp_mult, rare_drop_chance, color) VALUES
('Facil',    'facil',    0, 0.80, 0.70, 0.90, 0.80, 120, 1.0, 1.0, 0.05, '#4df3a3'),
('Normal',   'normal',   1, 1.00, 1.00, 1.00, 1.00, 150, 1.3, 1.3, 0.08, '#39c2ff'),
('Dificil',  'dificil',  2, 1.40, 1.30, 1.10, 1.30, 180, 1.7, 1.7, 0.12, '#ffd24d'),
('Extremo',  'extremo',  3, 2.00, 1.60, 1.20, 1.60, 210, 2.2, 2.2, 0.18, '#ff9f1c'),
('Insano',   'insano',   4, 3.00, 2.00, 1.35, 2.00, 240, 3.0, 3.0, 0.25, '#ff4d5e'),
('Pesadelo', 'pesadelo', 5, 4.50, 2.60, 1.50, 2.50, 300, 4.0, 4.0, 0.35, '#b14dff');

-- ---------- MAPAS (10) ----------
INSERT INTO maps (name, slug, description, theme, difficulty, main_enemies, width, height, bg_color, accent_color, obstacle_style, obstacle_count, enemy_multiplier, spawn_rate, has_hazards, reward_coins, reward_xp, unlock_level) VALUES
('Servidor Local',                       'servidor-local',    'Ambiente inicial de desenvolvimento. Racks de servidor servem de cobertura.',          'Ambiente inicial de desenvolvimento', 'facil',   'Virus, Bugs',                2000, 2000, '#06120c', '#2bbf7f', 'blocks',   18, 1.0, 1.00, 0, 100,  200, 1),
('Banco de Dados Corrompido',            'banco-corrompido',  'Tabelas SQL quebradas e registros infectados flutuam pelo mapa.',                      'Tabelas SQL quebradas',               'normal',  'Malware, Virus',             2200, 2200, '#0a0a16', '#3d7bff', 'tables',   26, 1.2, 1.15, 0, 150,  300, 2),
('Rede Infectada',                       'rede-infectada',    'Conexoes, cabos e pacotes de dados corrompidos. Inimigos trafegam veloz.',             'Conexoes e pacotes corrompidos',      'normal',  'Bots, Virus',                2400, 2400, '#0d0716', '#9b4dff', 'nodes',    22, 1.3, 1.35, 0, 200,  400, 3),
('Nuvem Instavel',                       'nuvem-instavel',    'Servidores em nuvem com falhas. Zonas de instabilidade causam dano.',                  'Servidores em nuvem com falhas',      'dificil', 'Malware, Trojan',            2400, 2400, '#101622', '#39c2ff', 'clouds',   16, 1.4, 1.30, 1, 280,  560, 5),
('Deep Web do Sistema',                  'deep-web',          'Area sombria e perigosa do sistema. Hordas de anomalias e glitches.',                  'Area sombria do sistema',             'dificil', 'Trojan, Ransomware',         2600, 2600, '#120608', '#ff3d5e', 'glitch',   30, 1.8, 1.60, 1, 360,  720, 7),
('Firewall Quebrado',                    'firewall-quebrado', 'Muralhas digitais destruidas. Tanques e barreiras corrompidas por toda parte.',        'Muralhas digitais destruidas',        'dificil', 'Firewall, Bots',             2600, 2600, '#160a04', '#ff7b3d', 'firewall', 24, 1.6, 1.40, 0, 440,  880, 9),
('Data Center Abandonado',               'data-center',       'Servidores antigos e maquinas esquecidas zumbis de processos mortos.',                 'Servidores antigos esquecidos',       'extremo', 'Firewall, Malware',          2800, 2800, '#0a0e10', '#7fd4c0', 'servers',  28, 1.8, 1.45, 1, 540, 1080, 12),
('Terminal Infinito',                    'terminal-infinito', 'Comandos, linhas de codigo e um terminal hacker sem fim.',                             'Terminal hacker infinito',            'extremo', 'Bots, Ransomware',           2800, 2800, '#020806', '#39ff88', 'terminal', 26, 2.0, 1.60, 0, 660, 1320, 15),
('Laboratorio de IA',                    'lab-ia',            'Inteligencia artificial fora de controle. Padroes hostis imprevisiveis.',              'IA fora de controle',                 'insano',  'Bots, Trojan, Malware',      3000, 3000, '#0c0618', '#c64dff', 'ai',       24, 2.2, 1.70, 1, 820, 1640, 18),
('Nucleo do Sistema',                    'nucleo-sistema',    'Fase final dentro do coracao do sistema. Tudo conspira contra voce.',                  'Coracao do sistema',                  'pesadelo','Tudo',                       3200, 3200, '#1a0008', '#ff0044', 'core',     32, 2.6, 1.90, 1, 1200, 2400, 22);

-- ---------- BOSSES (10, um por mapa na ordem acima) ----------
INSERT INTO bosses (name, slug, description, map_id, health, speed, damage, size, color, special_attack, xp_reward, coin_reward, score_reward) VALUES
('Bug Mestre',             'bug-mestre',          'O bug original que originou todos os outros.',                  1, 1200, 60, 26, 44, '#ff4d5e', 'summon',  400,  80,  1000),
('SQL Injection Vivo',     'sql-injection',       'Uma query maliciosa que ganhou vida propria.',                 2, 1600, 62, 30, 46, '#3d7bff', 'spread',  520,  110, 1400),
('Botnet Suprema',         'botnet-suprema',      'Mente coletiva de milhares de bots corrompidos.',              3, 2000, 70, 32, 46, '#9b4dff', 'summon',  650,  150, 1800),
('Cloud Phantom',          'cloud-phantom',       'Entidade que se materializa entre servidores em nuvem.',       4, 2600, 78, 36, 48, '#39c2ff', 'charge',  820,  200, 2400),
('Kernel Corrompido',      'kernel-corrompido',   'O nucleo comprometido. Dispara rajadas radiais devastadoras.', 5, 3200, 64, 40, 50, '#ff0044', 'radial',  1000, 260, 3000),
('Guardiao Firewall',      'guardiao-firewall',   'A ultima muralha, agora hostil. Resistencia absurda.',         6, 4200, 50, 44, 56, '#ff7b3d', 'spread',  1250, 320, 3600),
('Servidor Fantasma',      'servidor-fantasma',   'A alma de um data center que nunca foi desligado.',            7, 5200, 70, 46, 54, '#7fd4c0', 'charge',  1500, 400, 4400),
('Root Admin Corrompido',  'root-admin',          'Tem permissoes totais sobre o sistema. sudo rm -rf voce.',     8, 6400, 76, 50, 56, '#39ff88', 'laser',   1850, 480, 5200),
('Algoritmo Rebelde',      'algoritmo-rebelde',   'IA que decidiu que voce e o bug a ser removido.',              9, 8000, 82, 56, 60, '#c64dff', 'nova',    2300, 600, 6400),
('Mainframe Supremo',      'mainframe-supremo',   'O coracao do sistema. Chefe final. Boa sorte, dev.',          10, 12000, 70, 64, 70, '#ff0044', 'radial',  3500, 1000, 10000);

-- Liga cada mapa ao seu boss
UPDATE maps m JOIN bosses b ON b.map_id = m.id SET m.boss_id = b.id;

-- ---------- INIMIGOS COMUNS ----------
INSERT INTO enemies (name, slug, description, health, speed, damage, xp_reward, coin_reward, score_reward, size, color, behavior, spawn_weight, is_boss, min_time) VALUES
('Virus Comum',       'virus-comum',      'Rapido e fraco. Se replica pelo sistema.',                       18,  130, 6,  6,  1, 10,  14, '#ff4d5e', 'chase',       40, 0, 0),
('Malware',           'malware',          'Software malicioso de capacidade media.',                        45,  90,  12, 12, 2, 25,  18, '#ff7b3d', 'chase',       25, 0, 30),
('Trojan',            'trojan',           'Avanca devagar disfarcado, mas o golpe e devastador.',           70,  55,  30, 20, 3, 40,  22, '#c44dff', 'slow_heavy',  14, 0, 60),
('Ransomware',        'ransomware',       'Ao acertar voce, reduz sua velocidade temporariamente.',         110, 75,  18, 30, 5, 60,  24, '#ffd24d', 'slow_player',  8, 0, 120),
('Bug Critico',       'bug-critico',      'Aparece em enxames enormes.',                                    8,   150, 4,  3,  1, 5,   10, '#ff4da6', 'swarm',       18, 0, 20),
('Bot Corrompido',    'bot-corrompido',   'Unidade de botnet. Ataca em grupos coordenados.',                35,  110, 10, 10, 2, 20,  16, '#4dc4ff', 'group',       15, 0, 45),
('Firewall Quebrado', 'firewall-quebrado','Tanque digital com vida altissima.',                             300, 40,  22, 50, 8, 100, 32, '#ff3d3d', 'tank',         5, 0, 150);

-- ---------- ARMAS ----------
INSERT INTO weapons (name, slug, description, damage, fire_rate, bullet_speed, bullet_range, projectiles, spread, special, color, unlock_level) VALUES
('Teclado Mecanico',   'teclado-mecanico', 'Ataque basico de todo dev raiz.',                             12, 3.5, 420, 380, 1, 0.06, NULL,     '#4df3a3', 1),
('Comando Debug',      'comando-debug',    'Tiro preciso e devastador.',                                  34, 1.4, 650, 620, 1, 0.00, NULL,     '#39c2ff', 1),
('Firewall Blaster',   'firewall-blaster', 'Projetil que explode em area.',                               20, 1.0, 350, 340, 1, 0.00, 'area',   '#ff9f1c', 2),
('Script Automatico',  'script-automatico','Loop infinito de disparos rapidos.',                           7, 8.0, 500, 340, 1, 0.18, NULL,     '#c8ff4d', 2),
('Terminal Laser',     'terminal-laser',   'Feixe que atravessa todos os inimigos na linha.',            28, 0.8, 900, 700, 1, 0.00, 'laser',  '#ff4dd2', 3),
('Patch de Seguranca', 'patch-seguranca',  'Aplica um hotfix em voce, restaurando vida.',                25, 0.25, 0,   0,  0, 0.00, 'heal',   '#4dff88', 3),
('Framework Shield',   'framework-shield', 'Escudo temporario que absorve dano.',                        40, 0.15, 0,   0,  0, 0.00, 'shield', '#4d7dff', 4);

-- ---------- HABILIDADES ----------
INSERT INTO skills (name, slug, description, effect, effect_value, max_stacks, icon) VALUES
('Refatoracao Agil', 'velocidade',   '+10% de velocidade de movimento.',                  'speed',            0.10, 5, '>>'),
('Codigo Otimizado', 'dano',         '+15% de dano de todas as armas.',                   'damage',           0.15, 5, '++'),
('RAM Extra',        'vida-maxima',  '+20 de vida maxima (e cura 20).',                   'max_health',       20,   5, '[]'),
('Auto Save',        'regeneracao',  'Regenera 0.6 de vida por segundo.',                 'regen',            0.6,  5, '~~'),
('Thread Dupla',     'tiro-duplo',   'Suas armas disparam 1 projetil adicional.',         'double_shot',      1,    3, '||'),
('Try/Catch',        'escudo',       '+30 de escudo que se regenera fora de combate.',    'shield',           30,   3, '{}'),
('Stack Overflow',   'ataque-area',  'Pulso de energia periodico em area.',               'area_attack',      1,    3, '()'),
('Encapsulamento',   'reducao-dano', 'Reduz todo o dano recebido em 8%.',                 'damage_reduction', 0.08, 5, '##'),
('Pair Programming', 'xp-bonus',     '+20% de XP por inimigo derrotado.',                 'xp_boost',         0.20, 3, '<>'),
('Exploit Critico',  'critico',      '10% de chance de dano critico (x2).',               'crit_chance',      0.10, 4, '!!');

-- ---------- PERSONAGENS (10) ----------
INSERT INTO characters (name, slug, description, base_health, base_speed, base_damage, base_defense, passive_key, passive_value, passive_desc, special_name, special_key, special_desc, ultimate_name, ultimate_key, ultimate_desc, unlock_type, unlock_value, color) VALUES
('Dev Front-End',          'dev-frontend',  'Veloz e estiloso. Domina a interface do caos.',          95,  235, 1.00, 0.00, 'speed',          0.10, '+10% de velocidade',                'Dash Neon',              'dash',     'Avanco rapido na direcao da mira',         'Chuva de Componentes',  'rain',     'Rajada radial massiva de projeteis',          'default', 0,   '#39c2ff'),
('Dev Back-End',           'dev-backend',   'Solido e resistente. A base de tudo.',                   130, 195, 1.00, 0.10, 'defense',        0.08, '+8% de defesa',                     'Escudo de API',          'shield',   'Escudo temporario robusto',                'Deploy Seguro',         'bigheal',  'Cura total + escudo enorme',                  'coins',   800, '#4d7dff'),
('Dev Full Stack',         'dev-fullstack', 'Equilibrado em tudo. O canivete suico do codigo.',       110, 210, 1.10, 0.05, 'balanced',       0.05, 'Atributos equilibrados (+5% geral)','Combo de Codigo',        'rapid',    'Disparo rapido temporario',                'Sistema Integrado',     'buffnova', 'Buff total + nova de dano',                   'coins',   1200,'#4df3a3'),
('Hacker Etico',           'hacker-etico',  'Mais dano contra bots e malwares.',                      100, 215, 1.10, 0.03, 'anti_bot',       0.20, '+20% dano contra bots/malware',     'Invasao Controlada',     'rapid',    'Disparo rapido temporario',                'Exploit Reverso',       'nukeall',  'Dano massivo em todos (extra vs bots)',       'level',   30,  '#39ff88'),
('Engenheiro de Seguranca','eng-seguranca', 'Resistente a debuffs. Muralha viva.',                    140, 190, 0.95, 0.14, 'debuff_resist',  0.50, '-50% duracao de debuffs',           'Firewall Temporario',    'shield',   'Escudo temporario robusto',                'Protecao Absoluta',     'invuln',   'Invulnerabilidade por alguns segundos',       'coins',   1800,'#ff7b3d'),
('Cientista de Dados',     'cientista-dados','Ganha mais XP. Preve o caos.',                          100, 205, 1.00, 0.04, 'xp_gain',        0.25, '+25% de XP recebido',               'Previsao de Ataque',     'slowtime', 'Reduz a velocidade dos inimigos',          'Modelo Preditivo',      'slowxp',   'Congela e da grande burst de XP',             'level',   20,  '#c64dff'),
('DevOps',                 'devops',        'Recarrega habilidades mais rapido.',                     110, 210, 1.00, 0.06, 'cooldown',       0.30, '-30% de cooldown de habilidades',   'Deploy Rapido',          'dash',     'Avanco rapido na direcao da mira',         'Pipeline Supremo',      'rapidfire','Cooldowns zerados + auto-fire intenso',       'level',   75,  '#ffd24d'),
('Arquiteto de Software',  'arquiteto',     'Mais vida maxima. Construido para durar.',               170, 185, 1.00, 0.10, 'max_health',     0.20, '+20% de vida maxima',               'Refatoracao Defensiva',  'shield',   'Escudo temporario robusto',                'Arquitetura Imbativel', 'fortify',  'Vida maxima +, cura total e escudo',          'coins',   2500,'#ff4da6'),
('Programador Junior',     'junior',        'Ganha mais moedas. Aprendendo na marra.',                90,  215, 0.95, 0.02, 'coin_gain',      0.30, '+30% de moedas',                    'Ajuda da Comunidade',    'heal',     'Cura instantanea moderada',                'Stack Overflow Divino', 'clearcoins','Limpa inimigos fracos + chuva de moedas',     'coins',   600, '#c8ff4d'),
('Programador Lendario',   'lendario',      'Bonus geral em tudo. O mito, a lenda.',                  150, 225, 1.20, 0.12, 'all_bonus',      0.10, '+10% em todos os atributos',        'Codigo Perfeito',        'rapid',    'Disparo rapido temporario',                'Reset do Sistema',      'wipe',     'Elimina TODOS os inimigos na tela',           'level',   100, '#ffd700');

-- Da o personagem inicial (Dev Front-End) ao admin
INSERT INTO player_characters (user_id, character_id)
SELECT 1, id FROM characters WHERE unlock_type = 'default';

-- ---------- ITENS DA LOJA ----------
-- Skins de personagem (efeitos pequenos)
INSERT INTO store_items (name, slug, description, category, rarity, price, effect_key, effect_value, color) VALUES
('Dev Classico',        'skin-dev-classico',  'Visual padrao limpo. Sem bonus.',                       'character_skin', 'comum',    0,    'none',       0,    '#9fb3c8'),
('Dev Neon',            'skin-dev-neon',      'Brilho neon. +3% de velocidade.',                       'character_skin', 'incomum',  400,  'speed',      0.03, '#39ffd2'),
('Hacker Etico',        'skin-hacker-etico',  '+5% de dano contra bots e malware.',                    'character_skin', 'raro',     800,  'anti_bot',   0.05, '#39ff88'),
('Arquiteto de Software','skin-arquiteto',    '+5% de defesa.',                                        'character_skin', 'raro',     800,  'defense',    0.05, '#ff4da6'),
('Dev Fantasma',        'skin-dev-fantasma',  'Chance pequena de desviar de ataques.',                 'character_skin', 'epico',    1500, 'dodge',      0.07, '#b0c4de'),
('Cyber Dev',           'skin-cyber-dev',     '+5% de XP recebido.',                                   'character_skin', 'epico',    1500, 'xp',         0.05, '#c64dff'),
('Dev Lendario',        'skin-dev-lendario',  '+5% de dano e +5% de vida maxima.',                     'character_skin', 'lendario', 3000, 'hybrid',     0.05, '#ffd700');
-- Skins de arma (efeitos pequenos)
INSERT INTO store_items (name, slug, description, category, rarity, price, effect_key, effect_value, color) VALUES
('Teclado RGB',         'wskin-rgb',          'Tiros com rastro colorido (visual).',                   'weapon_skin', 'incomum',  350,  'trail',        0,    '#ff4da6'),
('Terminal Hacker',     'wskin-terminal',     '+3% de dano.',                                          'weapon_skin', 'raro',     700,  'damage',       0.03, '#39ff88'),
('Firewall Dourado',    'wskin-firewall-ouro','+5% de dano contra malwares.',                          'weapon_skin', 'epico',    1400, 'anti_malware', 0.05, '#ffd700'),
('Debug Congelante',    'wskin-congelante',   'Chance de reduzir a velocidade dos inimigos.',          'weapon_skin', 'epico',    1600, 'slow_chance',  0.15, '#39c2ff'),
('Script Flamejante',   'wskin-flamejante',   'Chance de causar dano continuo (queimadura).',          'weapon_skin', 'lendario', 2400, 'burn_chance',  0.15, '#ff7b3d'),
('Laser Quantico',      'wskin-quantico',     'Tiro atravessa um inimigo adicional.',                  'weapon_skin', 'mitico',   4000, 'pierce',       1,    '#c64dff');
-- Efeitos de personagem (aura)
INSERT INTO store_items (name, slug, description, category, rarity, price, effect_key, effect_value, color) VALUES
('Aura Verde',          'ceff-aura-verde',    'Aura neon verde ao redor do dev.',                      'char_effect', 'comum',   200, 'aura', 0, '#4df3a3'),
('Aura Roxa',           'ceff-aura-roxa',     'Aura mistica roxa.',                                    'char_effect', 'raro',    600, 'aura', 0, '#c64dff'),
('Aura Dourada',        'ceff-aura-dourada',  'Aura lendaria dourada.',                                'char_effect', 'lendario',2000,'aura', 0, '#ffd700');
-- Efeitos de tiro
INSERT INTO store_items (name, slug, description, category, rarity, price, effect_key, effect_value, color) VALUES
('Tiro Pixelado',       'seff-pixel',         'Projeteis em estilo pixel.',                            'shot_effect', 'comum',   200, 'shot', 0, '#c8ff4d'),
('Tiro Plasma',         'seff-plasma',        'Projeteis com brilho de plasma.',                       'shot_effect', 'epico',   900, 'shot', 0, '#39c2ff'),
('Tiro Estelar',        'seff-estelar',       'Projeteis com rastro de estrelas.',                     'shot_effect', 'mitico', 3000, 'shot', 0, '#ffd700');
-- Efeitos de eliminacao
INSERT INTO store_items (name, slug, description, category, rarity, price, effect_key, effect_value, color) VALUES
('Explosao de Bits',    'keff-bits',          'Inimigos explodem em bits ao morrer.',                  'kill_effect', 'incomum', 300, 'kill', 0, '#4df3a3'),
('Glitch Final',        'keff-glitch',        'Efeito de glitch na eliminacao.',                       'kill_effect', 'raro',    700, 'kill', 0, '#ff4d5e'),
('Desintegracao',       'keff-desintegrar',   'Inimigos se desintegram em particulas douradas.',       'kill_effect', 'lendario',2200,'kill', 0, '#ffd700');
-- Animacoes de entrada
INSERT INTO store_items (name, slug, description, category, rarity, price, effect_key, effect_value, color) VALUES
('Entrada Teleporte',   'ent-teleporte',      'Surge no mapa em um flash de teleporte.',               'entrance', 'incomum', 300, 'entrance', 0, '#39c2ff'),
('Entrada Boot',        'ent-boot',           'Materializa como um sistema dando boot.',               'entrance', 'epico',   1000,'entrance', 0, '#39ff88'),
('Entrada Lendaria',    'ent-lendaria',       'Entrada epica com onda de choque dourada.',             'entrance', 'lendario',2500,'entrance', 0, '#ffd700');
-- Molduras de perfil
INSERT INTO store_items (name, slug, description, category, rarity, price, effect_key, effect_value, color) VALUES
('Moldura Bronze',      'frame-bronze',       'Moldura de perfil bronze.',                             'frame', 'comum',    150,  'frame', 0, '#cd7f32'),
('Moldura Prata',       'frame-prata',        'Moldura de perfil prata.',                              'frame', 'incomum',  400,  'frame', 0, '#c0c0c0'),
('Moldura Ouro',        'frame-ouro',         'Moldura de perfil dourada.',                            'frame', 'raro',     900,  'frame', 0, '#ffd700'),
('Moldura Neon',        'frame-neon',         'Moldura de perfil neon animada.',                       'frame', 'epico',    1600, 'frame', 0, '#39ffd2'),
('Moldura Mitica',      'frame-mitica',       'Moldura de perfil mitica multicolorida.',              'frame', 'mitico',   3500, 'frame', 0, '#c64dff');
-- Pacotes de moedas (placeholder premium - desativados por padrao)
INSERT INTO store_items (name, slug, description, category, rarity, price, payload, active, color) VALUES
('Pacote 1000 Moedas',  'coins-1000',  'Pacote de moedas (versao premium - placeholder).', 'coin_pack', 'comum',   0, 1000, 0, '#ffd24d'),
('Pacote 5000 Moedas',  'coins-5000',  'Pacote de moedas (versao premium - placeholder).', 'coin_pack', 'raro',    0, 5000, 0, '#ffd24d');
-- Tickets de roleta (ao comprar, gira a roleta correspondente)
INSERT INTO store_items (name, slug, description, category, rarity, price, payload, color) VALUES
('Giro Roleta Comum',   'roul-common',    'Compra e gira a Roleta Comum imediatamente.',   'roulette', 'incomum',  300,  0, '#4df3a3'),
('Giro Roleta Rara',    'roul-rare',      'Compra e gira a Roleta Rara imediatamente.',    'roulette', 'raro',     800,  0, '#39c2ff'),
('Giro Roleta Lendaria','roul-legendary', 'Compra e gira a Roleta Lendaria imediatamente.','roulette', 'lendario', 2000, 0, '#ffd700');

-- ---------- EQUIPAMENTOS ----------
INSERT INTO equipment (name, slug, description, slot, rarity, price, bonus_key, bonus_value, color, unlock_level) VALUES
('Capacete de Debug',   'helmet-debug',  '+15 de vida maxima.',           'helmet', 'comum',   250,  'max_health', 15,   '#4df3a3', 1),
('Capacete Quantico',   'helmet-quantum','+40 de vida maxima.',           'helmet', 'epico',   1200, 'max_health', 40,   '#c64dff', 8),
('Armadura Firewall',   'armor-firewall','+8% de defesa.',                'armor',  'raro',    700,  'defense',    0.08, '#ff7b3d', 4),
('Armadura Kernel',     'armor-kernel',  '+15% de defesa.',               'armor',  'lendario',2000, 'defense',    0.15, '#ffd700', 12),
('Luvas de Compilacao', 'gloves-compile','+10% de dano.',                 'gloves', 'raro',    700,  'damage',     0.10, '#39ff88', 4),
('Luvas Overclock',     'gloves-oc',     '+20% de dano.',                 'gloves', 'lendario',2000, 'damage',     0.20, '#ff4d5e', 12),
('Botas de Latencia',   'boots-latency', '+8% de velocidade.',            'boots',  'incomum', 450,  'speed',      0.08, '#39c2ff', 2),
('Botas Turbo',         'boots-turbo',   '+18% de velocidade.',           'boots',  'epico',   1300, 'speed',      0.18, '#39ffd2', 9),
('Chip de XP',          'chip-xp',       '+20% de XP recebido.',          'chip',   'raro',    800,  'xp',         0.20, '#c8ff4d', 5),
('Chip de Cooldown',    'chip-cd',       '-15% de cooldown.',             'chip',   'epico',   1400, 'cooldown',   0.15, '#39c2ff', 10),
('Amuleto de Moedas',   'amulet-coins',  '+25% de moedas.',               'amulet', 'raro',    800,  'coins',      0.25, '#ffd700', 5),
('Amuleto Mitico',      'amulet-mitico', '+80 de vida maxima.',           'amulet', 'mitico',  3500, 'max_health', 80,   '#ff00aa', 20);

-- ---------- MEDALHAS (geradas: mapa x dificuldade) ----------
INSERT INTO medals (name, slug, kind, map_id, difficulty_id, icon, description)
SELECT CONCAT('Medalha ', m.name, ' - ', d.name),
       CONCAT('medal-', m.slug, '-', d.slug),
       'map_diff', m.id, d.id, '🏅',
       CONCAT('Concluiu ', m.name, ' na dificuldade ', d.name)
FROM maps m CROSS JOIN difficulty_modes d;
-- Medalha "Mestre" por mapa (todas as dificuldades)
INSERT INTO medals (name, slug, kind, map_id, difficulty_id, icon, description)
SELECT CONCAT('Mestre de ', m.name), CONCAT('master-', m.slug), 'map_master', m.id, NULL, '🥇',
       CONCAT('Concluiu ', m.name, ' em TODAS as dificuldades')
FROM maps m;
-- Medalha final suprema
INSERT INTO medals (name, slug, kind, map_id, difficulty_id, icon, description) VALUES
('Lenda Suprema do Sistema', 'supreme-legend', 'supreme', NULL, NULL, '👑', 'Concluiu todos os mapas em todas as dificuldades');

-- ---------- RECOMPENSAS POR NIVEL DE CONTA ----------
INSERT INTO account_level_rewards (level, reward_type, reward_slug, reward_amount, label, icon) VALUES
(5,   'coins',     NULL,                250,  '250 moedas',                 '💰'),
(10,  'skin',      'skin-dev-neon',     0,    'Skin Dev Neon',              '🎨'),
(15,  'coins',     NULL,                500,  '500 moedas',                 '💰'),
(20,  'weapon',    'terminal-laser',    0,    'Arma Terminal Laser',        '🔫'),
(30,  'character', 'hacker-etico',      0,    'Personagem Hacker Etico',    '🦸'),
(45,  'weapon_skin','wskin-firewall-ouro',0,  'Skin Firewall Dourado',      '🎨'),
(60,  'roulette',  'legendary',         0,    'Giro de Roleta Lendaria',    '🎰'),
(75,  'character', 'devops',            0,    'Personagem DevOps',          '🦸'),
(90,  'skin',      'skin-dev-lendario', 0,    'Skin Mitica (Dev Lendario)', '🎨'),
(100, 'character', 'lendario',          0,    'Programador Lendario',       '👑');

-- ---------- ROLETAS (loot tables) ----------
-- Roleta GRATIS diaria (premios modestos)
INSERT INTO roulette_rewards (roulette_type, reward_type, reward_slug, reward_amount, label, rarity, weight, color) VALUES
('free', 'coins', NULL, 50,  '50 moedas',  'comum',   40, '#ffd24d'),
('free', 'coins', NULL, 100, '100 moedas', 'comum',   25, '#ffd24d'),
('free', 'xp',    NULL, 100, '100 XP',     'comum',   20, '#39c2ff'),
('free', 'coins', NULL, 250, '250 moedas', 'incomum', 10, '#ffd24d'),
('free', 'skin',  'skin-dev-neon', 0, 'Skin Dev Neon', 'incomum', 5, '#39ffd2');
-- Roleta COMUM
INSERT INTO roulette_rewards (roulette_type, reward_type, reward_slug, reward_amount, label, rarity, weight, color) VALUES
('common', 'coins', NULL, 100, '100 moedas', 'comum',   35, '#ffd24d'),
('common', 'coins', NULL, 300, '300 moedas', 'comum',   25, '#ffd24d'),
('common', 'xp',    NULL, 200, '200 XP',     'comum',   20, '#39c2ff'),
('common', 'skin',  'skin-hacker-etico', 0, 'Skin Hacker Etico', 'raro', 10, '#39ff88'),
('common', 'weapon_skin', 'wskin-terminal', 0, 'Skin Terminal Hacker', 'raro', 7, '#39ff88'),
('common', 'effect', 'keff-bits', 0, 'Efeito Explosao de Bits', 'incomum', 3, '#4df3a3');
-- Roleta RARA
INSERT INTO roulette_rewards (roulette_type, reward_type, reward_slug, reward_amount, label, rarity, weight, color) VALUES
('rare', 'coins', NULL, 500, '500 moedas', 'incomum', 28, '#ffd24d'),
('rare', 'xp',    NULL, 500, '500 XP',     'incomum', 22, '#39c2ff'),
('rare', 'skin',  'skin-dev-fantasma', 0, 'Skin Dev Fantasma', 'epico', 16, '#b0c4de'),
('rare', 'weapon_skin', 'wskin-congelante', 0, 'Skin Debug Congelante', 'epico', 14, '#39c2ff'),
('rare', 'equipment', 'gloves-compile', 0, 'Luvas de Compilacao', 'raro', 12, '#39ff88'),
('rare', 'character', 'dev-backend', 0, 'Personagem Dev Back-End', 'epico', 8, '#4d7dff');
-- Roleta LENDARIA
INSERT INTO roulette_rewards (roulette_type, reward_type, reward_slug, reward_amount, label, rarity, weight, color) VALUES
('legendary', 'coins', NULL, 1000, '1000 moedas', 'raro',     22, '#ffd24d'),
('legendary', 'skin',  'skin-dev-lendario', 0, 'Skin Dev Lendario', 'lendario', 18, '#ffd700'),
('legendary', 'weapon_skin', 'wskin-quantico', 0, 'Skin Laser Quantico', 'mitico', 12, '#c64dff'),
('legendary', 'equipment', 'amulet-mitico', 0, 'Amuleto Mitico', 'mitico', 10, '#ff00aa'),
('legendary', 'effect', 'keff-desintegrar', 0, 'Efeito Desintegracao', 'lendario', 14, '#ffd700'),
('legendary', 'character', 'arquiteto', 0, 'Personagem Arquiteto', 'lendario', 14, '#ff4da6'),
('legendary', 'character', 'lendario', 0, 'Programador Lendario', 'mitico', 10, '#ffd700');

-- Loadout inicial do admin (equipa o personagem default, apenas 1)
INSERT INTO player_loadout (user_id, character_id)
SELECT 1, id FROM characters WHERE unlock_type = 'default' ORDER BY id LIMIT 1;

-- ============================================================
-- FIM DA INSTALACAO
-- ============================================================
