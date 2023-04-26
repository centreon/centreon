==============
Administration
==============

**********************
Advanced configuration
**********************

It is possible to overwrite default configuration of the module by creating/editing the
**/etc/centreon/centreon_dsmd.pm** file: ::

    %centreon_dsmd_config = (
        # which user will send action to Centcore
        centreon_user => 'centreon',
        # timeout to send command to Centcore
        submit_command_timeout => 5,
        # custom macro used to keep alarm ID
        macro_config => 'ALARM_ID',
        # number of alarms retrieve from the cache for analysis
        sql_fetch => 1000,
        # interval in seconds to clean locks
        clean_locks_time => 3600,
        # duration in seconds to keep locks
        clean_locks_keep_stored => 3600,
    );
    
    1;

*************
Purging cache
*************

All actions performed by the DSMD engine are logged in the database
**centreon_storage**. A cron is provided to delete the data based on retention.

To modify the retention period, by default **180 days**, you can create/edit the 
**/etc/centreon/centreon_dsm_purge.pm** file: ::

    %centreon_dsm_purge_config = (
        # period in days
        history_time => 180,
    );
    
    1;

To modify the hour of the cron job, you can edit the **/etc/cron.d/centreon-dsm**
file: ::

    #####################################
    # Centreon DSM
    #
    
    30 22 * * * root /usr/share/centreon/www/modules/centreon-dsm//cron/centreon_dsm_purge.pl --config='/etc/centreon/conf.pm' --severity=error >> /var/log/centreon/centreon_dsm_purge.log 2>&1

