export const labelHosts = 'Hosts';
export const labelName = 'Name';
export const labelAlias = 'Alias';
export const labelIpAddress = 'IP Address / DNS';
export const labelMonitoringServer = 'Monitoring server';
export const labelTemplates = 'Templates';
export const labelHostGroup = 'Host group';
export const labelHostTemplate = 'Host template';
export const labelStatus = 'Status';
// The plural the catalogue carries; `Host groups` is translated in two locales.
export const labelHostGroups = 'Hostgroups';
export const labelHostCategories = 'Host Categories';
export const labelParentHosts = 'Parent Hosts';
export const labelChildHosts = 'Child Hosts';

export const labelGoToServices = 'Display all Services for this host';
export const labelDeployServices = 'Deploy Service';
export const labelServicesDeployed = 'Services deployed successfully!';
export const labelServiceDeploymentFailed = 'Service deployment failed.';

export const labelWelcomeToHosts = 'Welcome to the Hosts interface!';
export const labelCreateHost = 'Create a host';

// The sections of the US, spelled as the catalogue already spells them: the
// legacy host form names its tabs this way, so all six locales have them.
export const labelHostConfiguration = 'Host Configuration';
export const labelNotification = 'Notification';
export const labelRelations = 'Relations';
export const labelDataProcessing = 'Data Processing';
export const labelHostExtendedInfos = 'Host Extended Infos';

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

// Yes / No / Default fields
export const labelYes = 'Yes';
export const labelNo = 'No';
export const labelDefault = 'Default';

// Notification, spelled as the legacy host form spells it
export const labelNotificationEnabled = 'Notification Enabled';
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
export const labelDown = 'Down';
export const labelUnreachable = 'Unreachable';
export const labelRecovery = 'Recovery';
export const labelFlapping = 'Flapping';
export const labelDowntimeScheduled = 'Downtime Scheduled';
export const labelNone = 'None';
