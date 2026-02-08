export const labelAgentsConfigurations = 'Agent configurations';
export const labelWelcomeToTheAgentsConfigurationPage =
  'Welcome to the agent configuration page';
export const labelCreate = 'Create';
export const labelName = 'Name';
export const labelAgentType = 'Agent type';
export const labelPoller = 'Poller';
export const labelAction = 'Action';
export const labelExpand = 'Expand';
export const labelCollapse = 'Collapse';
export const labelAddNewAgent = 'Add new agent configuration';
export const labelSearch = 'Search';
export const labelFilters = 'Filters';
export const labelPollers = 'Pollers';
export const labelClear = 'Clear';
export const labelDeletePoller = 'Delete poller';
export const labelDeleteAgent = 'Delete agent configuration';
export const labelCancel = 'Cancel';
export const labelDelete = 'Delete';
export const labelPollerConfiguration = 'Poller configuration';
export const labelRequired = 'Required';
export const labelAddAgentConfiguration = 'Add agent configuration';
export const labelAdd = 'Add';
export const labelOTelServer = 'OTLP receiver';
export const labelPort = 'Port';
export const labelSave = 'Save';
export const labelPortExpectedAtMost = 'Port number must be at most 65535';
export const labelPortMustStartFrom1 = 'Port number must be at least 1';
export const labelExtensionNotAllowed = 'Extension not allowed';
export const labelPublicCertificate = 'Public certificate (.crt, .cert, .cer)';
export const labelCaCertificate = 'CA (.crt, .cert, .cer)';
export const labelPrivateKey = 'Private key (.key)';
export const labelOTLPReceiver = 'OTLP Receiver';
export const labelAddressInvalid = 'Invalid address';
export const labelMonitoredHosts = 'Monitored hosts';
export const labelAddAHost = 'Add a host';
export const labelParameters = 'Parameters';
export const labelDNSIP = 'DNS/IP';
export const labelSelectHost = 'Select host';

export const labelAgent = 'Agent';
export const labelConfigurationServer = 'Configuration provider';
export const labelAgentConfigurationCreated = 'Agent configuration created';
export const labelAgentConfigurationUpdated = 'Agent configuration updated';
export const labelUpdateAgentConfiguration = 'Update agent configuration';
export const labelRelativePathAreNotAllowed = 'Relative paths are not allowed';
export const labelInvalidPath = 'Invalid path';
export const labelInvalidExtension = 'Invalid extension';
export const labelInvalidFilename = 'Invalid filename';

export const labelWelcomeDescription =
  'An agent is a piece of software you install on the host you want to monitor, that executes the checks.';
export const labelCMA = 'Centreon Monitoring Agent';
export const labelPollerCaCertificateFileName =
  'Poller CA certificate file name';
export const labelPollerCaName = 'Poller CA name';

export const labelDeletePollerConfirmation =
  'You are going to delete the configuration for the <strong>{{ poller }}</strong> poller from the <strong>{{ agent }}</strong> agent configuration. All configuration parameters for this poller will be deleted. This action cannot be undone.';

export const labelDeleteAgentConfirmation =
  'You are going to delete the <strong>{{ agent }}</strong> agent configuration. All parameters for this agent configuration will be deleted. This action cannot be undone.';

export const labelCACommonName = 'CA Common Name (CN)';

export const labelEncryptionLevel = 'Encryption level';
export const labelWarningEncryptionLevelTelegraf =
  'You have selected No TLS for the encryption level.';
export const labelWarningEncryptionLevelCMA =
  'You have selected No TLS for the encryption level. This parameter is meant for test purposes only and is not allowed in production. The agent monitoring will stop after 1 hour.';

export const labelTLS = 'TLS';
export const labelNoTLS = 'No TLS';
export const labelInsecure = 'Insecure TLS';

export const labelCMAauthenticationToken = 'CMA authentication token(s)';
export const labelSelectExistingCMATokens = 'Select existing CMA token(s)';
export const labelSelectExistingCMAToken = 'Select existing CMA token';
export const labelCreateNewCMAToken = 'Create new CMA token';

export const labelEnable = 'Enable';
export const labelConnectionInitiated = 'Connection initiated';
export const labelByAgent = 'By agent';
export const labelByPoller = 'By poller';
export const labelByAgentTooltip =
  'This is the most common case: the agent initiates the connection to the poller.';
export const labelByPollerTooltip =
  'If the agent is not allowed to connect to the poller for security reasons (e.g. when the poller is in a DMZ), you can use a poller-initiated connection.';

export const labelAtLeastOneConnexionMode =
  'At least one connection mode must be enabled.';

export const labelSelectAtLeastOneColumn =
  'At least one column must be selected';

// commands
export const labelGenerateInstallationCommand =
  'Generate your CMA installation command';
export const labelCommandWarning =
  'The command is only valid for hosts with an agent-initiated connection to this poller.';
export const labelSelectPollerThatWillMonitor =
  'Select the poller that will monitor your hosts';
export const labelSelectPoller = 'Select poller';
export const labelSelectOperatingSystem = "Select your hosts' operating system";
export const labelWindows = 'Windows';
export const labelLinux = 'Linux';
export const labelDownloadTheScript = 'Download the script';
export const labelDownload = 'Download';
export const labelThenCopyTheScript =
  'then copy the script to each host you want to monitor.';
export const labelExecuteTheScript = 'Execute the script';
export const labelRunTheFollowingCommand =
  'Run the following command on each host you want to monitor with the agent.';
export const labelCopyCommand = 'Copy command';
export const labelCommandCopied = 'Command copied!';
export const labelFailedToCopyTheCommand = 'Failed to copy the command!';
