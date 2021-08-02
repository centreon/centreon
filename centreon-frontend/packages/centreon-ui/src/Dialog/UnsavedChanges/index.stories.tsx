import * as React from 'react';

import { useSnackbar } from '../..';
import withSnackbar from '../../Snackbar/withSnackbar';

import UnsavedChangesDialog from '.';

export default { title: 'Dialog/Unsaved Changes Dialog' };

interface Props {
  isSubmitting: boolean;
  isValidForm: boolean;
}

const Story = ({ isValidForm, isSubmitting }: Props): JSX.Element => {
  const { showInfoMessage } = useSnackbar();

  const closeDialog = () => showInfoMessage('Close');

  const discardChanges = () => showInfoMessage('Discard');

  const saveChanges = () => showInfoMessage('Save');

  return (
    <UnsavedChangesDialog
      dialogOpened
      closeDialog={closeDialog}
      discardChanges={discardChanges}
      isSubmitting={isSubmitting}
      isValidForm={isValidForm}
      saveChanges={saveChanges}
    />
  );
};

const StoryWithSnackbar = withSnackbar({ Component: Story });

export const normal = (): JSX.Element => (
  <StoryWithSnackbar isValidForm isSubmitting={false} />
);

export const withNotValidForm = (): JSX.Element => (
  <StoryWithSnackbar isSubmitting={false} isValidForm={false} />
);

export const submitting = (): JSX.Element => (
  <StoryWithSnackbar isSubmitting isValidForm />
);
