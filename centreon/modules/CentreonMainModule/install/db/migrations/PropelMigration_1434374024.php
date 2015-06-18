<?php

/**
 * Data object containing the SQL and PHP code to migrate the database
 * up to version 1434374024.
 * Generated on 2015-06-15 15:13:44 by root
 */
class PropelMigration_1434374024
{

    public function preUp($manager)
    {
        // add the pre-migration code here
    }

    public function postUp($manager)
    {
        // add the post-migration code here
    }

    public function preDown($manager)
    {
        // add the pre-migration code here
    }

    public function postDown($manager)
    {
        // add the post-migration code here
    }

    /**
     * Get the SQL statements for the Up migration
     *
     * @return array list of the SQL strings to execute for the Up migration
     *               the keys being the datasources
     */
    public function getUpSQL()
    {
        return array (
  'centreon' => '
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `cfg_bam` DROP FOREIGN KEY `mod_bam_ibfk_4`;

ALTER TABLE `cfg_bam` ADD CONSTRAINT `mod_bam_ibfk_4`
    FOREIGN KEY (`icon_id`)
    REFERENCES `cfg_binaries` (`binary_id`)
    ON DELETE SET NULL;
    


# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
',
);
    }

    /**
     * Get the SQL statements for the Down migration
     *
     * @return array list of the SQL strings to execute for the Down migration
     *               the keys being the datasources
     */
    public function getDownSQL()
    {
        return array (
  'centreon' => '
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `cfg_bam` DROP FOREIGN KEY `mod_bam_ibfk_4`;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
',
);
    }

}