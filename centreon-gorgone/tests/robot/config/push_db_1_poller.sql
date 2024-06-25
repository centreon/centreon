INSERT IGNORE INTO `nagios_server`
  VALUES
  (
    1, 'Central', '1', 1, 1711560733, '127.0.0.1',
    '1', '0', 'service centengine start',
    'service centengine stop', 'service centengine restart',
    'service centengine reload', '/usr/sbin/centengine',
    '/usr/sbin/centenginestats', '/var/log/centreon-engine/service-perfdata',
    'service cbd reload', '/etc/centreon-broker',
    '/usr/share/centreon/lib/centreon-broker',
    '/usr/lib64/centreon-connector',
    22, '1', 5556, 'centreontrapd', '/etc/snmp/centreon_traps/',
    NULL, NULL, NULL, NULL, '1', '0'
  ),
  (
    2, 'pushpoller', '0', 0, NULL, '127.0.0.1',
    '1', '0', 'service centengine start',
    'service centengine stop', 'service centengine restart',
    'service centengine reload', '/usr/sbin/centengine',
    '/usr/sbin/centenginestats', '/var/log/centreon-engine/service-perfdata',
    'service cbd reload', '/etc/centreon-broker',
    '/usr/share/centreon/lib/centreon-broker',
    '/usr/lib64/centreon-connector',
    22, '1', 5556, 'centreontrapd', '/etc/snmp/centreon_traps/',
    NULL, NULL, '/var/log/centreon-broker/',
    NULL, '1', '0'
  );
INSERT IGNORE INTO `cfg_nagios`
  VALUES
  (
    1, 'Centreon Engine Central', NULL,
    '/var/log/centreon-engine/centengine.log',
    '/etc/centreon-engine', '/var/log/centreon-engine/status.dat',
    60, '1', '1', '1', '1', '1', '1', '1',
    4096, '1s', '/var/lib/centreon-engine/rw/centengine.cmd',
    '1', '/var/log/centreon-engine/retention.dat',
    60, '1', '1', '0', '1', '1', '1', '1',
    NULL, '1', '1', NULL, NULL, NULL, 's',
    's', 's', 0, 15, 15, 5, '0', NULL, NULL,
    '0', '25.0', '50.0', '25.0', '50.0',
    '0', 60, 12, 30, 30, '1', '1', '0', NULL,
    NULL, '0', NULL, 'euro', 30, '~!$%^&*\"|\'<>?,()=',
    '`~$^&\"|\'<>', '0', '0', 'admin@localhost',
    'admin@localhost', 'Centreon Engine configuration file for a central instance',
    '1', '-1', 1, '1', '1', 15, 15, NULL,
    '0', 15, '/var/log/centreon-engine/centengine.debug',
    0, '0', '1', 1000000000, 'centengine.cfg',
    '1', '0', '', 'log_v2_enabled'
  ),
  (
    15, 'pushpoller', NULL, '/var/log/centreon-engine/centengine.log',
    '/etc/centreon-engine/', '/var/log/centreon-engine/status.dat',
    60, '1', '1', '1', '1', '1', '1', '1',
    4096, '1s', '/var/lib/centreon-engine/rw/centengine.cmd',
    '1', '/var/log/centreon-engine/retention.dat',
    60, '1', '0', '0', '1', '1', '1', '1',
    '1', '1', '1', NULL, NULL, '0.5', 's',
    's', 's', 0, 15, 15, 5, '0', 30, 180, '0',
    '25.0', '50.0', '25.0', '50.0', '0',
    60, 30, 30, 30, '1', '1', '0', NULL, NULL,
    '0', NULL, 'euro', 30, '~!$%^&*\"|\'<>?,()=',
    '`~$^&\"|\'<>', '0', '0', 'admin@localhost',
    'admin@localhost', 'Centreon Engine config file for a polling instance',
    '1', '-1', 2, '1', '1', 15, 15, NULL,
    '0', 15, '/var/log/centreon-engine/centengine.debug',
    0, '0', '1', 1000000000, 'centengine.cfg',
    '1', '0', '', 'log_v2_enabled'
  );
