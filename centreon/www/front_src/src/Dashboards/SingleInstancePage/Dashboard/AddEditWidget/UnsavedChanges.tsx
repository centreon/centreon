import { useFormikContext } from 'formik';

import { UnsavedChangesDialog } from '@centreon/ui';

interface UnsavedChangesProps {
  closeDialog: () => void;
  discard: () => void;
  opened: boolean;
}

const UnsavedChanges = ({
  opened,
  closeDialog,
  discard
}: UnsavedChangesProps): JSX.Element => {
  const { dirty, isValid, handleSubmit } = useFormikContext();

  const isValidForm = dirty && isValid;

  return (
    <UnsavedChangesDialog
      closeDialog={closeDialog}
      dialogOpened={opened}
      discardChanges={discard}
      isSubmitting={false}
      isValidForm={isValidForm}
      saveChanges={handleSubmit}
    />
  );
};

export default UnsavedChanges;
