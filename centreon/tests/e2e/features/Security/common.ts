// Message printed by a legacy QuickForm when its centreon_token is not valid
const invalidTokenMessage =
  'The form has not been submitted since 15 minutes. Please retry to resubmit';

// Value the legacy forms display instead of a stored secret
const maskedSecretValue = '**********';

const apiV2Base = '/centreon/api/latest';

// Monitoring configuration generated and deployed by Centreon
const generatedConfigurationDirectories = [
  '/etc/centreon-broker',
  '/etc/centreon-engine',
  '/var/cache/centreon/config'
];

const generatedHostsFile = '/var/cache/centreon/config/engine/1/hosts.cfg';

const encryptedValuePrefix = 'encrypt::';

const buildHostCountQuery = (hostName: string): string =>
  `SELECT COUNT(*) AS count FROM host WHERE host_name = '${hostName}' AND host_register = '1'`;

const buildHostIdQuery = (hostName: string): string =>
  `SELECT host_id FROM host WHERE host_name = '${hostName}' AND host_register = '1'`;

const buildHostAcknowledgedQuery = (hostName: string): string =>
  `SELECT acknowledged FROM hosts WHERE name = '${hostName}' AND enabled = 1`;

// Files of the directories holding one of the values as plain text
const buildFilesContainingCommand = (
  directories: Array<string>,
  values: Array<string>
): string =>
  `grep -rlF ${values.map((value) => `-e '${value}'`).join(' ')} ${directories.join(' ')} 2>/dev/null || true`;

// Value of a custom macro in the definition of a host of an Engine hosts file
const hostMacroValue = (
  hostsFile: string,
  hostName: string,
  macroName: string
): string | null => {
  const definition = hostsFile
    .split('define host')
    .find((block) => new RegExp(`\\bhost_name\\s+${hostName}\\s`).test(block));
  const line = definition
    ?.split('\n')
    .find((row) => row.trim().startsWith(`_${macroName} `));

  return line ? (line.trim().split(/\s+/)[1] ?? null) : null;
};

// A form-urlencoded body with some fields replaced (or removed when null)
const withFormFields = (
  body: string,
  fields: Record<string, string | null>
): string => {
  const params = new URLSearchParams(body);

  Object.entries(fields).forEach(([name, value]) => {
    if (value === null) {
      params.delete(name);

      return;
    }

    params.set(name, value);
  });

  return params.toString();
};

export {
  apiV2Base,
  buildFilesContainingCommand,
  buildHostAcknowledgedQuery,
  buildHostCountQuery,
  buildHostIdQuery,
  encryptedValuePrefix,
  generatedConfigurationDirectories,
  generatedHostsFile,
  hostMacroValue,
  invalidTokenMessage,
  maskedSecretValue,
  withFormFields
};
