import { getCommandsWithArguments } from '.';

describe(getCommandsWithArguments, () => {
  it('parses the command and the associated arguments', () => {
    expect(
      getCommandsWithArguments(
        "/usr/lib/centreon/plugins/centreon_ruckus_scg_snmp.pl --plugin=network::ruckus::scg::snmp::plugin --mode=memory --hostname=snmpsim.centreon.training --snmp-version='2c' --snmp-community='ruckus_scg' --warning-usage='80' --critical-usage='90' -d -p 50 | grep warning"
      )
    ).toEqual([
      {
        arguments: [],
        command: '/usr/lib/centreon/plugins/centreon_ruckus_scg_snmp.pl'
      },
      {
        arguments: [],
        command: 'plugin=network::ruckus::scg::snmp::plugin'
      },
      {
        arguments: [],
        command: 'mode=memory'
      },
      {
        arguments: [],
        command: 'hostname=snmpsim.centreon.training'
      },
      {
        arguments: [],
        command: "snmp-version='2c'"
      },
      {
        arguments: [],
        command: "snmp-community='ruckus_scg'"
      },
      {
        arguments: [],
        command: "warning-usage='80'"
      },
      {
        arguments: [['-d'], ['-p', '50'], ['|', 'grep'], ['warning']],
        command: "critical-usage='90'"
      }
    ]);
  });
});
