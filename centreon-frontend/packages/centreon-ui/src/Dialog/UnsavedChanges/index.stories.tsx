import * as React from 'react';

import { useSnackbar } from '../..';
import Severity from '../../Snackbar/Severity';
import withSnackbar from '../../Snackbar/withSnackbar';

import UnsavedChangesDialog from '.';

export default { title: 'Dialog/Unsaved Changes Dialog' };

interface Props {
  isSubmitting: boolean;
  isValidForm: boolean;
}

const Story = ({ isValidForm, isSubmitting }: Props): JSX.Element => {
  const { showMessage } = useSnackbar();

  const closeDialog = () =>
    showMessage({ message: 'Close', severity: Severity.info });

  const discardChanges = () =>
    showMessage({ message: 'Discard', severity: Severity.info });

  const saveChanges = () =>
    showMessage({ message: 'Save', severity: Severity.info });

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

const StoryWithSnackbar = withSnackbar(Story);

export const normal = (): JSX.Element => (
  <StoryWithSnackbar isValidForm isSubmitting={false} />
);

export const withNotValidForm = (): JSX.Element => (
  <StoryWithSnackbar isSubmitting={false} isValidForm={false} />
);

export const submitting = (): JSX.Element => (
  <StoryWithSnackbar isSubmitting isValidForm />
);
