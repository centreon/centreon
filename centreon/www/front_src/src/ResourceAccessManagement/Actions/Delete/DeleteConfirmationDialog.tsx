import { useTranslation } from 'react-i18next';

import { ConfirmDialog } from '@centreon/ui';

import {
  labelCancel,
  labelDelete,
  labelDeleteResourceAccessRule,
  labelDeleteResourceAccessRuleDialogMessage,
  labelDeleteResourceAccessRuleWarning
} from '../../translatedLabels';

import useDeleteConfirmationDialogStyles from './DeleteConfirmationDialog.styles';
import useDelete from './useDelete';

const DeleteConfirmationDialog = (): JSX.Element => {
  const { classes } = useDeleteConfirmationDialogStyles();
  const { t } = useTranslation();

  const {
    closeDialog,
    isDialogOpen,
    isLoading,
    submit,
    resourceAccessRuleName
  } = useDelete();

  return (
    <ConfirmDialog
      confirmDisabled={isLoading}
      dialogConfirmButtonClassName={classes.confimButton}
      dialogPaperClassName={classes.paper}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDelete)}
      labelMessage={
        resourceAccessRuleName &&
        `The ${resourceAccessRuleName} ${t(labelDeleteResourceAccessRuleDialogMessage)}.`
      }
      labelSecondMessage={t(labelDeleteResourceAccessRuleWarning)}
      labelTitle={t(labelDeleteResourceAccessRule)}
      open={isDialogOpen}
      submitting={isLoading}
      onCancel={closeDialog}
      onConfirm={submit}
    />
  );
};

export default DeleteConfirmationDialog;
