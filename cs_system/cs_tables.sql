
/* =========================================================
   Buwana Chat Support System — Full Schema
   ========================================================= */

------------------------------------------------------------
-- 1) cs_chats_tb
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cs_chats_tb (
                                           id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                           user_id BIGINT UNSIGNED NOT NULL,
                                           app_id INT(11) NOT NULL,
    language_id VARCHAR(10) NOT NULL DEFAULT 'EN',

    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,

    status ENUM('open','in_progress','resolved','closed')
    NOT NULL DEFAULT 'open',
    priority ENUM('low','medium','high','urgent')
    DEFAULT 'medium',

    category VARCHAR(100),
    assigned_to BIGINT UNSIGNED,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    resolved_at DATETIME NULL,
    closed_at DATETIME NULL,

    INDEX (user_id),
    INDEX (app_id),
    INDEX (assigned_to),
    INDEX (status),
    INDEX (priority),
    INDEX (language_id),

    CONSTRAINT fk_cs_chats_language
    FOREIGN KEY (language_id)
    REFERENCES languages_tb(language_id)
    ON UPDATE CASCADE
    ON DELETE SET DEFAULT,

    CONSTRAINT fk_cs_chats_app
    FOREIGN KEY (app_id)
    REFERENCES apps_tb(app_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
    );


------------------------------------------------------------
-- 2) cs_messages_tb
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cs_messages_tb (
                                              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                              chat_id BIGINT UNSIGNED NOT NULL,
                                              user_id BIGINT UNSIGNED NOT NULL,
                                              language_id VARCHAR(10) NOT NULL DEFAULT 'EN',

    body TEXT NOT NULL,
    parent_id BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    INDEX (chat_id),
    INDEX (user_id),
    INDEX (parent_id),
    INDEX (language_id),
    INDEX (created_at),

    CONSTRAINT fk_cs_messages_language
    FOREIGN KEY (language_id)
    REFERENCES languages_tb(language_id)
    ON UPDATE CASCADE
    ON DELETE SET DEFAULT
    );


------------------------------------------------------------
-- 3) cs_attachments_tb
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cs_attachments_tb (
                                                 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                                 chat_id BIGINT UNSIGNED NOT NULL,
                                                 message_id BIGINT UNSIGNED NULL,

                                                 file_url TEXT NOT NULL,
                                                 file_type VARCHAR(50),

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX (chat_id),
    INDEX (message_id),
    INDEX (created_at)
    );


------------------------------------------------------------
-- 4) cs_chat_upvotes_tb
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cs_chat_upvotes_tb (
                                                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                                  chat_id BIGINT UNSIGNED NOT NULL,
                                                  user_id BIGINT UNSIGNED NOT NULL,
                                                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                                  UNIQUE KEY unique_vote (chat_id, user_id),

    INDEX (chat_id),
    INDEX (user_id)
    );


------------------------------------------------------------
-- 5) cs_chat_tags_tb
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cs_chat_tags_tb (
                                               id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                               name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,

    description VARCHAR(255),

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
    );


------------------------------------------------------------
-- 6) cs_chat_tag_map_tb
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cs_chat_tag_map_tb (
                                                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                                  chat_id BIGINT UNSIGNED NOT NULL,
                                                  tag_id BIGINT UNSIGNED NOT NULL,

                                                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                                  UNIQUE KEY unique_chat_tag (chat_id, tag_id),

    INDEX (chat_id),
    INDEX (tag_id)
    );


------------------------------------------------------------
-- 7) cs_chat_readers_tb
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cs_chat_readers_tb (
                                                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                                  chat_id BIGINT UNSIGNED NOT NULL,
                                                  user_id BIGINT UNSIGNED NOT NULL,

                                                  last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                                  UNIQUE KEY unique_reader (chat_id, user_id),

    INDEX (chat_id),
    INDEX (user_id),
    INDEX (last_read_at)
    );


------------------------------------------------------------
-- 8) cs_message_reads_tb
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cs_message_reads_tb (
                                                   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                                   message_id BIGINT UNSIGNED NOT NULL,
                                                   user_id BIGINT UNSIGNED NOT NULL,

                                                   read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                                   UNIQUE KEY unique_reader (message_id, user_id),

    INDEX (message_id),
    INDEX (user_id),
    INDEX (read_at)
    );


------------------------------------------------------------
-- Default seed tags
------------------------------------------------------------
INSERT IGNORE INTO cs_chat_tags_tb (name, slug, description)
VALUES
    ('bug',        'bug',        'Report of broken or incorrect behavior'),
    ('feedback',   'feedback',   'General user feedback'),
    ('suggestion', 'suggestion', 'Suggested improvement or feature'),
    ('question',   'question',   'User inquiry'),
    ('congrats',   'congrats',   'Positive message or celebration'),
    ('other',      'other',      'Uncategorized message');

