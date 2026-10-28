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

export const labelModalTitle = ({
  action,
  type
}: {
  action: string;
  type: string;
}) => {
  const article = /^[aeiou]/i.test(type) ? 'an' : 'a';
  return `${action} ${article} ${type}`;
};

export const labelDeleteResource = (type: string) => `Delete ${type}`;
export const labelDuplicateResource = (type: string) => `Duplicate ${type}`;

// API messages
export const labelResourceDisabled = (type: string) => `${type} disabled`;
export const labelResourceEnabled = (type: string) => `${type} enabled`;
export const labelResourceDuplicated = (type: string) => `${type} duplicated`;
export const labelResourceDeleted = (type: string) => `${type} deleted`;
export const labelResourceCreated = (type: string) => `${type} created`;
export const labelResourceUpdated = (type: string) => `${type} updated`;

export const labelFailedToDeleteResources = (type: string) =>
  `Failed to delete the ${type}`;
export const labelFailedToDuplicateResources = (type: string) =>
  `Failed to duplicate the ${type}`;
export const labelFailedToEnableResources = (type: string) =>
  `Failed to enable the ${type}`;
export const labelFailedToDisableResources = (type: string) =>
  `Failed to disable the ${type}`;

export const labelFailedToDeleteSomeResources = 'Failed to delete';
export const labelFailedToDuplicateSomeResources = 'Failed to duplicate';
export const labelFailedToEnableSomeResources = 'Failed to enable';
export const labelFailedToDisableSomeResources = 'Failed to disable';

// dialogs content
export const labelDeleteResourceConfirmation = (type: string) =>
  `You are about to delete the <strong>{{ name }}</strong> ${type}. This action cannot be undone. Do you want to delete it?`;

export const labelDeleteResourcesConfirmation = (type: string) =>
  `You are about to delete <strong>{{ count }} ${type}.</strong> This action cannot be undone. Do you want to delete them?`;

export const labelDuplicateResourceConfirmation = (type: string) =>
  `You are about to duplicate the <strong>{{ name }}</strong> ${type}. How many duplications would you like to make?`;
export const labelDuplicateResourcesConfirmation = (type: string) =>
  `You are about to duplicate <strong>{{ count }} ${type}.</strong> How many duplications would you like to make?`;

export const labelSingleDuplicateResourceConfirmation = (type: string) =>
  `You are about to duplicate the <strong>{{ name }}</strong> ${type}.`;
export const labelSingleDuplicateResourcesConfirmation = (type: string) =>
  `You are about to duplicate <strong>{{ count }} ${type}.</strong>`;

// Form
export const labelSave = 'Save';

export const labelClose = 'Close';
export const labelReset = 'Reset';
export const labelDoYouWantToResetTheForm = 'Do you want to reset the form?';
export const labelMassChange = 'Mass Change';
export const labelApply = 'Apply';
export const labelApplyChangesTo = (type: string) =>
  `The changes will be applied to {{count}} ${type}.`;
export const labelFailedToUpdateResources = (type: string) =>
  `Failed to update the ${type}`;
// Followed by the names of the rows that failed.
export const labelFailedToUpdateSomeResources = 'An error occurred';

// Custom macros
export const labelAddNewEntry = 'Add new entry';
export const labelValue = 'Value';
export const labelPassword = 'Password';
export const labelRevertToPreviousPassword = 'Revert to previous password';
export const labelTemplateInheritance = 'Template inheritance';
export const labelCommandInheritance = 'Command inheritance';
export const labelRequired = 'Required';
export const labelAlreadyExists = 'Already exists';
export const labelReservedMacroName = 'This name is reserved';
export const labelMustBeAtMostCharacters =
  '{{label}} can be at most {{max}} characters';
