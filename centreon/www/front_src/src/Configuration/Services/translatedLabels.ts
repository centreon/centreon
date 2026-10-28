export const labelServices = 'Services';
// Wording to confirm with UX, on the model of the Hosts subtitle.
export const labelServicesDescription =
  'Configure the services monitored on your hosts';
export const labelHost = 'Host';
export const labelName = 'Name';
export const labelScheduling = 'Scheduling';
export const labelTemplate = 'Template';
export const labelServiceTemplate = 'Service template';
export const labelHostName = 'Host name';
export const labelStatus = 'Status';
export const labelIncludeDisabledHosts = 'Include disabled hosts';

// Durations of the Scheduling column: an interval converted to seconds, then
// to minutes when it falls on a whole minute.
export const labelDurationInMinutes = '{{count}} min';
export const labelDurationInSeconds = '{{count}} sec';

export const labelWelcomeToServices = 'Welcome to the Services interface!';
export const labelCreateService = 'Create a service';

// Form sections, in the order of the legacy tabs. The legacy service form
// already carries these strings, so every locale has them.
export const labelServiceConfiguration = 'Service Configuration';
export const labelNotifications = 'Notifications';
export const labelRelations = 'Relations';
export const labelDataProcessing = 'Data Processing';
export const labelServiceExtendedInfo = 'Service Extended Info';

// MON-213082: Service Configuration, name, host, template and scheduling.
// Spelled as the legacy service form and the Hosts form spell them.
export const labelCheckPeriod = 'Check Period';
export const labelMaxCheckAttempts = 'Max Check Attempts';
export const labelNormalCheckInterval = 'Normal Check Interval';
export const labelRetryCheckInterval = 'Retry Check Interval';
export const labelActiveChecksEnabled = 'Active Checks Enabled';
export const labelPassiveChecksEnabled = 'Passive Checks Enabled';
export const labelIsVolatile = 'Is Volatile';
export const labelRequired = 'Required';
// What the legacy forms raise when a name fails the character check.
export const labelNameContainsForbiddenCharacters = 'Unauthorized value';
export const labelMustBeIntegerOfAtLeastOne =
  'An integer with a minimum value of 1 is required';
export const labelMustBeAtMostCharacters =
  '{{label}} can be at most {{max}} characters';
