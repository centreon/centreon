import { useTranslation } from 'react-i18next';

import { ConfirmDialog } from '@centreon/ui';

import {
  labelCancel,
  labelDelete,
  labelDeleteResourceAccessRule,
  labelDeleteResourceAccessRuleDialogMessage,
  labelDeleteResourceAccessRuleWarning,
  labelDeleteResourceAccessRules,
  labelDeleteResourceAccessRulesDialogMessage,
  labelDeleteResourceAccessRulesWarning
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
        resourceAccessRuleName
          ? `The ${resourceAccessRuleName} ${t(labelDeleteResourceAccessRuleDialogMessage)}`
          : t(labelDeleteResourceAccessRulesDialogMessage)
      }
      labelSecondMessage={
        resourceAccessRuleName
          ? t(labelDeleteResourceAccessRuleWarning)
          : t(labelDeleteResourceAccessRulesWarning)
      }
      labelTitle={
        resourceAccessRuleName
          ? t(labelDeleteResourceAccessRule)
          : t(labelDeleteResourceAccessRules)
      }
      open={isDialogOpen}
      submitting={isLoading}
      onCancel={closeDialog}
      onConfirm={submit}
    />
  );
};

export default DeleteConfirmationDialog;
