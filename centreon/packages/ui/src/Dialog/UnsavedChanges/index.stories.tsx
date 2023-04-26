import { ComponentMeta, ComponentStory } from '@storybook/react';

import { useSnackbar } from '../..';
import SnackbarProvider from '../../Snackbar/SnackbarProvider';

import UnsavedChangesDialog from '.';

export default {
  argTypes: {
    isSubmitting: { control: 'boolean' },
    isValidForm: { control: 'boolean' }
  },
  component: UnsavedChangesDialog,
  title: 'Dialog/Unsaved Changes Dialog'
} as ComponentMeta<typeof UnsavedChangesDialog>;

interface Props {
  isSubmitting: boolean;
  isValidForm: boolean;
}

const Story = ({ isValidForm, isSubmitting }: Props): JSX.Element => {
  const { showInfoMessage } = useSnackbar();

  const closeDialog = (): void => showInfoMessage('Close');

  const discardChanges = (): void => showInfoMessage('Discard');

  const saveChanges = (): void => showInfoMessage('Save');

  return (
    <SnackbarProvider>
      <UnsavedChangesDialog
        dialogOpened
        closeDialog={closeDialog}
        discardChanges={discardChanges}
        isSubmitting={isSubmitting}
        isValidForm={isValidForm}
        saveChanges={saveChanges}
      />
    </SnackbarProvider>
  );
};

const TemplateUnsavedChangesDialog: ComponentStory<
  typeof UnsavedChangesDialog
> = (args) => <Story {...args} />;

export const PlaygroundUnsavedChangesDialog = TemplateUnsavedChangesDialog.bind(
  {}
);

export const normal = (): JSX.Element => (
  <Story isValidForm isSubmitting={false} />
);

export const withNotValidForm = (): JSX.Element => (
  <Story isSubmitting={false} isValidForm={false} />
);

export const submitting = (): JSX.Element => <Story isSubmitting isValidForm />;
