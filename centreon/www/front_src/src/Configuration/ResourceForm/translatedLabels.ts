// The labels of the blocks every monitoring-resource form shares. The form's
// fields are spelled as the design spells them.

// Section and block titles.
export const labelNotifications = 'Notifications';
export const labelArguments = 'Arguments';
export const labelContactsToNotify = 'Contacts to notify';
export const labelNotifyOn = 'Notify on';
export const labelAutoRemediation = 'Auto-remediation';
export const labelCommand = 'Command';
export const labelFreshnessCheck = 'Freshness check';
export const labelDataProcessing = 'Data processing';
export const labelFlapDetection = 'Flap detection';

// Form validation
export const labelRequired = 'Required';
export const labelMustBePositiveIntegerOrZero =
  'Must be a positive integer or 0';
export const labelMustBeIntegerOfAtLeastOne =
  'An integer with a minimum value of 1 is required';
export const labelMustBeAtMostCharacters =
  '{{label}} can be at most {{max}} characters';
export const labelInvalidGeographicCoordinates = 'geo coords are not valid';

export const labelSnmpCommunity = 'SNMP community';
export const labelSnmpVersion = 'Version';
export const labelTimezone = 'Timezone';
export const labelCheckCommand = 'Check command';
export const labelCheckPeriod = 'Check time period';
export const labelMaxCheckAttempts = 'Max check attempts';
export const labelNormalCheckInterval = 'Check interval';
export const labelRetryCheckInterval = 'Retry interval';
export const labelActiveChecksEnabled = 'Enable active checks';
export const labelPassiveChecksEnabled = 'Enable passive checks';

// Yes / No / third state fields: the design words the third state per field.
export const labelYes = 'Yes';
export const labelNo = 'No';
export const labelDefault = 'Default';

// Notification
export const labelNotificationEnabled = 'Enable notifications';
export const labelContactAdditiveInheritance = 'Add to inherited contacts';
export const labelContactGroupAdditiveInheritance =
  'Add to inherited contact groups';
export const labelLinkedContacts = 'Linked contacts';
export const labelLinkedContactGroups = 'Linked contact groups';
export const labelNotificationInterval = 'Notify again every';
export const labelNotificationPeriod = 'Notify during';
export const labelFirstNotificationDelay = 'First notification delay';
export const labelRecoveryNotificationDelay = 'Recovery notification delay';
export const labelNone = 'None';

// Under the `Freshness check` title, the design says only `Enable`.
export const labelEnableFreshnessCheck = 'Enable';
export const labelFreshnessThreshold = 'Freshness threshold';
export const labelAcknowledgementTimeout = 'Acknowledgement timeout';
export const labelFlapDetectionEnabled = 'Enable flap detection';
export const labelLowFlapThreshold = 'Low threshold';
export const labelHighFlapThreshold = 'High threshold';
export const labelEventHandler = 'Event handler';
export const labelEventHandlerEnabled = 'Enable event handler';
// Legacy's label for the arguments of the check command and the event handler.
export const labelArgs = 'Args';
export const labelSeconds = 'seconds';
// The unit of the delays the engine counts in intervals of one minute.
export const labelTimesSixtySeconds = '* 60 seconds';

// Extended infos
export const labelNote = 'Note';
export const labelNoteUrl = 'Note URL';
export const labelActionUrl = 'Action URL';
export const labelIcon = 'Icon';
export const labelAltIcon = 'Alt icon';
export const labelGeographicCoordinates = 'Geographic coordinates';
export const labelComments = 'Comments';

// Mass change
export const labelUpdateMode = 'Update mode';
export const labelIncremental = 'Incremental';
export const labelReplacement = 'Replacement';
