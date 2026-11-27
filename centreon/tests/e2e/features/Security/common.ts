// Message printed by a legacy QuickForm when its centreon_token is not valid
const invalidTokenMessage =
  'The form has not been submitted since 15 minutes. Please retry to resubmit';

// Value the legacy forms display instead of a stored secret
const maskedSecretValue = '**********';

const apiV2Base = '/centreon/api/latest';

// Files holding credentials: generated monitoring configuration and Centreon settings
const secretFileDirectories = [
  '/etc/centreon',
  '/etc/centreon-broker',
  '/etc/centreon-engine',
  '/etc/centreon-gorgone',
  '/var/cache/centreon/config'
];

const buildHostCountQuery = (hostName: string): string =>
  `SELECT COUNT(*) AS count FROM host WHERE host_name = '${hostName}' AND host_register = '1'`;

const buildHostIdQuery = (hostName: string): string =>
  `SELECT host_id FROM host WHERE host_name = '${hostName}' AND host_register = '1'`;

const buildHostAcknowledgedQuery = (hostName: string): string =>
  `SELECT acknowledged FROM hosts WHERE name = '${hostName}' AND enabled = 1`;

// Regular files of the directories that other users can read
const buildWorldReadableFilesCommand = (directories: Array<string>): string =>
  `find ${directories.join(' ')} -type f -perm -o=r 2>/dev/null || true`;

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
  buildHostAcknowledgedQuery,
  buildHostCountQuery,
  buildHostIdQuery,
  buildWorldReadableFilesCommand,
  invalidTokenMessage,
  maskedSecretValue,
  secretFileDirectories,
  withFormFields
};
