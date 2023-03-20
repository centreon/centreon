--
-- Contenu de la table view_img_dir
--
INSERT INTO `view_img_dir` (`dir_name`, `dir_alias`, `dir_comment`)
VALUES ('logos', 'logos', 'my logos');

--
-- Contenu de la table view_img_dir
--
INSERT INTO `view_img` (`img_name`, `img_path`, `img_comment`)
VALUES ('centreon', 'logo-centreon-colors.png', 'centreon logo colors');

INSERT INTO `view_img` (`img_name`, `img_path`, `img_comment`)
VALUES ('centreon white', 'logo-centreon-white.png', 'centreon logo white');

--
-- Contenu de la table view_img_dir_relation
--
INSERT INTO `view_img_dir_relation` (`dir_dir_parent_id`, `img_img_id`)
VALUES (1, 1);