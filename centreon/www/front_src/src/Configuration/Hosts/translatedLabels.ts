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
export const labelResolve = 'Resolve FQDN';
export const labelHostNotFound = 'Host not found';

export const labelGoToServices = 'Display all Services for this host';
export const labelDeployServices = 'Deploy Service';
export const labelServicesDeployed = 'Services deployed successfully!';
export const labelServiceDeploymentFailed = 'Service deployment failed.';

export const labelWelcomeToHosts = 'Welcome to the Hosts interface!';
export const labelCreateHost = 'Create a host';

// The sections of the form, as the design names them.
export const labelGeneralInformation = 'General information';
export const labelHostMonitoringProperties = 'Host monitoring properties';
export const labelRelations = 'Relations';
export const labelMonitoringEngineSchedulingOptions =
  'Monitoring engine / Scheduling options';
export const labelAdvanced = 'Advanced';

// Titled blocks within them, as the design names them.
export const labelHostBasicInformation = 'Host basic information';
export const labelHostCheckOptions = 'Host check options';
// The design capitalises the title, not the field (`labelHostGroups`).
export const labelParentChildRelations = 'Parent-child relations';
export const labelHostDetails = 'Host details';
export const labelSchedulingOptions = 'Scheduling options';

// Form validation
// The message the legacy host form raises on the same rule.
export const labelNameMustNotStartWithModule =
  '_Module_ is not a legal expression';
// The constants say which rule failed; the wording is the catalogue's, so the
// six locales we ship already carry it. `Unauthorized value` is what the
// legacy host form raises when a name fails the same character check.
export const labelNameContainsForbiddenCharacters = 'Unauthorized value';
export const labelInvalidAddress = 'Not a valid IP address';
export const labelParentAndChildHost = 'Circular Definition';
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

// What a host notifies on.
export const labelDown = 'Down';
export const labelUnreachable = 'Unreachable';
export const labelRecovery = 'Recovery';
export const labelFlapping = 'Flapping';
export const labelDowntimeScheduled = 'Scheduled downtime';

export const labelHostSeverity = 'Host severity';
export const labelTemplateReplacementDeletesServices =
  'Replacing the templates will delete all the services created by the previous templates on every selected host.';

// The shared blocks' labels, which the Hosts pages and tests read from here.
export {
  labelAcknowledgementTimeout,
  labelActionUrl,
  labelActiveChecksEnabled,
  labelAltIcon,
  labelArgs,
  labelArguments,
  labelAutoRemediation,
  labelCheckCommand,
  labelCheckPeriod,
  labelCommand,
  labelComments,
  labelContactAdditiveInheritance,
  labelContactGroupAdditiveInheritance,
  labelContactsToNotify,
  labelDataProcessing,
  labelDefault,
  labelEnableFreshnessCheck,
  labelEventHandler,
  labelEventHandlerEnabled,
  labelFirstNotificationDelay,
  labelFlapDetection,
  labelFlapDetectionEnabled,
  labelFreshnessCheck,
  labelFreshnessThreshold,
  labelGeographicCoordinates,
  labelHighFlapThreshold,
  labelIcon,
  labelIncremental,
  labelInvalidGeographicCoordinates,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelLowFlapThreshold,
  labelMaxCheckAttempts,
  labelMustBeAtMostCharacters,
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero,
  labelNo,
  labelNone,
  labelNormalCheckInterval,
  labelNote,
  labelNoteUrl,
  labelNotificationEnabled,
  labelNotificationInterval,
  labelNotificationPeriod,
  labelNotifications,
  labelNotifyOn,
  labelPassiveChecksEnabled,
  labelRecoveryNotificationDelay,
  labelReplacement,
  labelRequired,
  labelRetryCheckInterval,
  labelSeconds,
  labelSnmpCommunity,
  labelSnmpVersion,
  labelTimesSixtySeconds,
  labelTimezone,
  labelUpdateMode,
  labelYes
} from '../ResourceForm/translatedLabels';
