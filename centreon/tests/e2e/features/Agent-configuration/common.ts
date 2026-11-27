// Freshness threshold forced on the objects using a Centreon Monitoring Agent
// command (Command::CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD)
const cmaFreshnessThreshold = 500;

// A check command is seen as a Centreon Monitoring Agent one when it is locked
// (installed by a monitoring connector) and its name holds this marker
const cmaCommandNameMarker = 'Centreon-Monitoring-Agent';

const buildLockCommandQuery = (commandName: string): string =>
  `UPDATE command SET command_locked = 1 WHERE command_name = '${commandName}'`;

const buildHostIdByNameQuery = (name: string, isTemplate: boolean): string =>
  `SELECT host_id AS id FROM host WHERE host_name = '${name}' AND host_register = '${isTemplate ? 0 : 1}'`;

const buildServiceIdByNameQuery = (name: string, isTemplate: boolean): string =>
  `SELECT service_id AS id FROM service WHERE service_description = '${name}' AND service_register = '${isTemplate ? 0 : 1}'`;

export {
  buildHostIdByNameQuery,
  buildLockCommandQuery,
  buildServiceIdByNameQuery,
  cmaCommandNameMarker,
  cmaFreshnessThreshold
};
