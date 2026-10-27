export const labelHosts = 'Hosts';
export const labelHostsDescription =
  'Configure the hosts monitored by your platform.';
export const labelName = 'Name';
export const labelAlias = 'Alias';
export const labelIpAddress = 'IP Address / DNS';
// The form's field, as the design names it; the listing keeps `labelIpAddress`.
export const labelAddress = 'Address';
export const labelMonitoringServer = 'Monitoring server';
export const labelTemplates = 'Templates';
export const labelHostGroup = 'Host group';
export const labelHostTemplate = 'Host template';
export const labelStatus = 'Status';
// The form's fields, as the design names them.
export const labelHostGroups = 'Host groups';
export const labelHostCategories = 'Host categories';
export const labelParentHosts = 'Parent hosts';
export const labelChildHosts = 'Child hosts';
export const labelResolve = 'FQDN resolve';
export const labelHostNotFound = 'Host not found';

export const labelGoToServices = 'Display all Services for this host';
export const labelDeployServices = 'Deploy Service';
export const labelServicesDeployed = 'Services deployed successfully!';
export const labelServiceDeploymentFailed = 'Service deployment failed.';

export const labelWelcomeToHosts = 'Welcome to the Hosts interface!';
export const labelCreateHost = 'Create a host';

// The sections of the form, as the design names them.
export const labelGeneralInformation = 'General Information';
export const labelHostMonitoringProperties = 'Host Monitoring Properties';
export const labelRelations = 'Relations';
export const labelMonitoringEngineSchedulingOptions =
  'Monitoring Engine / Scheduling Options';
export const labelNotifications = 'Notifications';
export const labelAdvanced = 'Advanced';

// Titled blocks within them, as the design names them.
export const labelHostBasicInformation = 'Host basic information';
export const labelHostCheckOptions = 'Host check options';
export const labelArguments = 'Arguments';
// The design capitalises the title, not the field (`labelHostGroups`).
export const labelHostGroupsTitle = 'Host Groups';
export const labelParentChildRelations = 'Parent-child relations';
export const labelHostDetails = 'Host details';
export const labelSchedulingOptions = 'Scheduling options';
export const labelContactsToNotify = 'Contacts to notify';
export const labelNotifyOn = 'Notify on';
export const labelAutoRemediation = 'Auto-remediation';
export const labelCommand = 'Command';
export const labelFreshnessCheck = 'Freshness check';
export const labelDataProcessing = 'Data Processing';
export const labelFlapDetection = 'Flap detection';

// Form validation
export const labelRequired = 'Required';
// The message the legacy host form raises on the same rule.
export const labelNameMustNotStartWithModule =
  '_Module_ is not a legal expression';
// The constants say which rule failed; the wording is the catalogue's, so the
// six locales we ship already carry it. `Unauthorized value` is what the
// legacy host form raises when a name fails the same character check.
export const labelNameContainsForbiddenCharacters = 'Unauthorized value';
export const labelInvalidAddress = 'Not a valid IP address';
export const labelParentAndChildHost = 'Circular Definition';
export const labelMustBePositiveIntegerOrZero =
  'Must be a positive integer or 0';
export const labelMustBeIntegerOfAtLeastOne =
  'An integer with a minimum value of 1 is required';

export const labelSnmpCommunity = 'SNMP Community';
export const labelSnmpVersion = 'Version';
export const labelTimezone = 'Timezone';
// The form's fields from here on are spelled as the design spells them.
export const labelCheckCommand = 'Check command';
export const labelCheckPeriod = 'Check time period';
export const labelMaxCheckAttempts = 'Max check attempts';
export const labelNormalCheckInterval = 'Check interval';
export const labelRetryCheckInterval = 'Retry interval';
export const labelActiveChecksEnabled = 'Enable active checks';
export const labelPassiveChecksEnabled = 'Enable passive checks';
// Templates. The listing filter keeps `labelHostTemplate`.
export const labelSelectATemplate = 'Select a template';
export const labelAddNewEntry = 'Add new entry';
export const labelEditTemplate = 'Modify';
export const labelCreateServicesLinkedToTemplates =
  'Automatically create services from templates';
// Custom macros.
export const labelCustomMacros = 'Custom macros';
export const labelValue = 'Value';
export const labelPassword = 'Password';
export const labelClear = 'Clear';
export const labelRevertToPreviousPassword = 'Revert to previous password';
export const labelAlreadyExists = 'Already exists';
export const labelTemplateInheritance = 'Template inheritance';
export const labelCommandInheritance = 'Command inheritance';

// Yes / No / third state fields: the design words the third state per field.
export const labelYes = 'Yes';
export const labelNo = 'No';
export const labelDefault = 'Default';

// Notification
export const labelNotificationEnabled = 'Notification Enable';
export const labelContactAdditiveInheritance = 'Add to inherited contacts';
export const labelContactGroupAdditiveInheritance =
  'Add to inherited contact groups';
export const labelLinkedContacts = 'Linked contacts';
export const labelLinkedContactGroups = 'Linked contact groups';
export const labelNotificationInterval = 'Notify again every';
export const labelNotificationPeriod = 'Notify during';
export const labelFirstNotificationDelay = 'First notification delay';
export const labelRecoveryNotificationDelay = 'Recovery notification delay';
export const labelDown = 'Down';
export const labelUnreachable = 'Unreachable';
export const labelRecovery = 'Recovery';
export const labelFlapping = 'Flapping';
export const labelDowntimeScheduled = 'Scheduled downtime';
export const labelNone = 'None';

// Under the `Freshness check` title, the design says only `Enable`.
export const labelEnableFreshnessCheck = 'Enable';
export const labelFreshnessThreshold = 'Freshness threshold';
export const labelAcknowledgementTimeout = 'Acknowledgement';
export const labelFlapDetectionEnabled = 'Flap Detection Enable';
export const labelLowFlapThreshold = 'Low threshold';
export const labelHighFlapThreshold = 'High threshold';
export const labelEventHandler = 'Event Handler';
export const labelEventHandlerEnabled = 'Event Handler Enable';
// Legacy's label for the arguments of the check command and the event handler.
export const labelArgs = 'Args';
export const labelSeconds = 'seconds';

// Host Extended Infos
export const labelNote = 'Note';
export const labelNoteUrl = 'Note URL';
export const labelActionUrl = 'Action URL';
export const labelIcon = 'Icon';
export const labelAltIcon = 'Alt icon';
export const labelGeographicCoordinates = 'Geographic coordinates';
export const labelHostSeverity = 'Host severity';
export const labelComments = 'Comments';
export const labelInvalidGeographicCoordinates = 'geo coords are not valid';
export const labelMustBeAtMostCharacters =
  '{{label}} can be at most {{max}} characters';
export const labelUpdateMode = 'Update mode';
export const labelIncremental = 'Incremental';
export const labelReplacement = 'Replacement';
export const labelTemplateReplacementDeletesServices =
  'Replacing the templates will delete all the services created by the previous templates on every selected host.';
