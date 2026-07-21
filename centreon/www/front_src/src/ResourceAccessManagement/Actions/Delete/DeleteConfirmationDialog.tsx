import { ConfirmDialog } from '@centreon/ui';

import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  labelCancel,
  labelDelete,
  labelDeleteResourceAccessRule,
  labelDeleteResourceAccessRuleDialogMessage,
  labelDeleteResourceAccessRules,
  labelDeleteResourceAccessRulesDialogMessage,
  labelDeleteResourceAccessRulesWarning,
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

  const dialogMessage = isNil(resourceAccessRuleName)
    ? t(labelDeleteResourceAccessRulesDialogMessage)
    : `${resourceAccessRuleName} ${t(labelDeleteResourceAccessRuleDialogMessage)}`;

  const dialogSecondMessage = isNil(resourceAccessRuleName)
    ? t(labelDeleteResourceAccessRulesWarning)
    : t(labelDeleteResourceAccessRuleWarning);

  const dialogTitle = isNil(resourceAccessRuleName)
    ? t(labelDeleteResourceAccessRules)
    : t(labelDeleteResourceAccessRule);

  return (
    <ConfirmDialog
      confirmDisabled={isLoading}
      dialogConfirmButtonClassName={classes.confimButton}
      dialogContentTextProps={{ component: 'div' }}
      dialogPaperClassName={classes.paper}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDelete)}
      labelMessage={dialogMessage}
      labelSecondMessage={dialogSecondMessage}
      labelTitle={dialogTitle}
      onCancel={closeDialog}
      onConfirm={submit}
      open={isDialogOpen}
      submitting={isLoading}
    />
  );
};

export default DeleteConfirmationDialog;
