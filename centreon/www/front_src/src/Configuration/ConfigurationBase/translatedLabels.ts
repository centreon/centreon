export const labelSelectAtLeastOneColumn =
  'At least one column must be selected';

export const labelName = 'Name';
export const labelActions = 'Actions';
export const labelAlias = 'Alias';
export const labelSearch = 'Search';

export const labelAdd = 'Add';
export const labelDelete = 'Delete';
export const labelDuplicate = 'Duplicate';
export const labelEnable = 'Enable';
export const labelDisable = 'Disable';
export const labelEnabled = 'Enabled';
export const labelDisabled = 'Disabled';
export const labelCancel = 'Cancel';
export const labelFilters = 'Filters';
export const labelClear = 'Clear';
export const labelStatus = 'Status';
export const labelMoreActions = 'More actions';
export const labelDuplications = 'Duplications';
export const labelEnableDisable = 'Enable/Disable';

// actions
export const labelAddResource = (type) => `Add a ${type}`;
export const labelUpdateResource = (type) => `Modify a ${type}`;

export const labelDeleteResource = (type) => `Delete ${type}`;
export const labelDuplicateResource = (type) => `Duplicate ${type}`;

// API messages
export const labelResourceDisabled = (type) => `${type} disabled`;
export const labelResourceEnabled = (type) => `${type} enabled`;
export const labelResourceDuplicated = (type) => `${type} duplicated`;
export const labelResourceDeleted = (type) => `${type} deleted`;

export const labelFailedToDeleteResources = (type) =>
  `Failed to delete the ${type}`;
export const labelFailedToDuplicateResources = (type) =>
  `Failed to duplicate the ${type}`;
export const labelFailedToEnableResources = (type) =>
  `Failed to enable the ${type}`;
export const labelFailedToDisableResources = (type) =>
  `Failed to disable the ${type}`;

export const labelFailedToDeleteSomeResources = 'Failed to delete';
export const labelFailedToDuplicateSomeResources = 'Failed to duplicate';
export const labelFailedToEnableSomeResources = 'Failed to enable';
export const labelFailedToDisableSomeResources = 'Failed to disable';

// dialogs content
export const labelDeleteResourceConfirmation = (type) =>
  `You are about to delete the <strong>{{ name }}</strong> ${type}. This action cannot be undone. Do you want to delete it?`;

export const labelDeleteResourcesConfirmation = (type) =>
  `You are about to delete <strong>{{ count }} ${type}.</strong> This action cannot be undone. Do you want to delete them?`;

export const labelDuplicateResourceConfirmation = (type) =>
  `You are about to duplicate the <strong>{{ name }}</strong> ${type}. How many duplications would you like to make?`;
export const labelDuplicateResourcesConfirmation = (type) =>
  `You are about to duplicate <strong>{{ count }} ${type}.</strong> How many duplications would you like to make?`;
