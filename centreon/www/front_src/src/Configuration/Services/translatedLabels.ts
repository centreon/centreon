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
// MON-213086: Notifications section (F15–F25). Spelled as the Hosts form
// spells them, or as the legacy service form does, so the locales carry them.
export const labelNotificationEnabled = 'Notification Enabled';
export const labelInheritContactsFromHost =
  'Inherit only contacts/contacts group from host';
export const labelContactAdditiveInheritance = 'Contact additive inheritance';
export const labelContactGroupAdditiveInheritance =
  'Contact group additive inheritance';
export const labelLinkedContacts = 'Linked Contacts';
export const labelLinkedContactGroups = 'Linked Contact Groups';
export const labelNotificationInterval = 'Notification Interval';
export const labelNotificationPeriod = 'Notification Period';
export const labelNotificationOptions = 'Notification Options';
export const labelFirstNotificationDelay = 'First notification delay';
export const labelRecoveryNotificationDelay = 'Recovery notification delay';
export const labelWarning = 'Warning';
export const labelUnknown = 'Unknown';
export const labelCritical = 'Critical';
export const labelRecovery = 'Recovery';
export const labelFlapping = 'Flapping';
export const labelDowntimeScheduled = 'Downtime Scheduled';
export const labelNone = 'None';
export const labelMustBePositiveIntegerOrZero =
  'Must be a positive integer or 0';
// MON-213084: Relations section. The legacy service form's msgids, so every
// locale already has them.
export const labelServiceGroups = 'Service groups';
export const labelServiceTraps = 'Service Trap Relation';
// Data Processing (MON-213085), spelled as the legacy service form spells it.
export const labelYes = 'Yes';
export const labelNo = 'No';
export const labelDefault = 'Default';
export const labelObsessOverService = 'Obsess Over Service';
export const labelAcknowledgementTimeout = 'Acknowledgement timeout';
export const labelFreshnessControlOptions = 'Freshness Control options';
export const labelCheckFreshness = 'Check Freshness';
export const labelFreshnessThreshold = 'Freshness Threshold';
export const labelSeconds = 'seconds';
export const labelFlappingOptions = 'Flapping options';
export const labelFlapDetectionEnabled = 'Flap Detection Enabled';
export const labelLowFlapThreshold = 'Low Flap Threshold';
export const labelHighFlapThreshold = 'High Flap Threshold';
export const labelHistoryOptions = 'History Options';
export const labelRetainStatusInformation = 'Retain Status Information';
export const labelRetainNonStatusInformation = 'Retain Non Status Information';
export const labelStalkingOptions = 'Stalking Options';
export const labelOk = 'Ok';
export const labelEventHandler = 'Event Handler';
export const labelEventHandlerEnabled = 'Event Handler Enabled';
// Legacy's label for the arguments of the check command and the event handler.
export const labelArgs = 'Args';
// The catalogue already carries these two, from the Hosts form.
// New strings, wording to confirm with UX.
export const labelMustBeIntegerBetweenZeroAndOneHundred =
  'Must be an integer between 0 and 100';
export const labelEventHandlerArgumentsNeedAnEventHandler =
  'Arguments need an event handler';
export const labelForbiddenEventHandlerArguments =
  'The following arguments are not valid: {{arguments}}';
// MON-213087: Extended Info section (F41-F50). Prefixed with `Service` so
// they cannot clash with the constants other sections append. The legacy
// form carries every field name, and the Hosts form the validation messages.
export const labelServiceGraphTemplate = 'Graph Template';
export const labelServiceCategories = 'Service Categories';
export const labelServiceSeverity = 'Service severity';
// Wording to confirm: a severity option, as the legacy select shows it.
export const labelServiceSeverityWithLevel = '{{name}} ({{level}})';
export const labelServiceNote = 'Note';
export const labelServiceNoteUrl = 'Note URL';
export const labelServiceActionUrl = 'Action URL';
export const labelServiceIcon = 'Icon';
export const labelServiceAltIcon = 'Alt icon';
export const labelServiceGeographicCoordinates = 'Geographic coordinates';
export const labelServiceComments = 'Comments';
export const labelServiceInvalidGeographicCoordinates =
  'geo coords are not valid';
export const labelServiceMustBeAtMostCharacters =
  '{{label}} can be at most {{max}} characters';
// MON-213083: check command, arguments and macros (F04–F06). The first four
// are legacy msgids, already translated.
export const labelCheckCommand = 'Check Command';
export const labelCustomMacros = 'Custom macros';
export const labelFillAllArgumentsOrNone =
  'You must either fill all the arguments or leave them all empty';
// Wording to confirm with UX: no legacy string says it.
export const labelArgumentsForbiddenTokens =
  'Arguments must not contain #BR#, #T# or #R#';
