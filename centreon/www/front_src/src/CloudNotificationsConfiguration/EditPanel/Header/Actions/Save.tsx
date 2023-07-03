import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { FormikValues, useFormikContext } from 'formik';
import { or } from 'ramda';

import { Box } from '@mui/material';
import SaveIcon from '@mui/icons-material/SaveOutlined';

import { ConfirmDialog, IconButton } from '@centreon/ui';

import {
  labelSave,
  labelDoYouWantToConfirmAction
} from '../../../translatedLabels';
import useFormSubmit from '../../useFormSubmit';

const useStyle = makeStyles()((theme) => ({
  icon: {
    fontSize: theme.spacing(2.5)
  }
}));

const SaveAction = (): JSX.Element => {
  const { classes } = useStyle();
  const { t } = useTranslation();

  const { isSubmitting, isValid, dirty, submitForm } =
    useFormikContext<FormikValues>();

  const { labelConfirm, labelCancel, setDialogOpen, dialogOpen } =
    useFormSubmit();

  const onClick = (): void => setDialogOpen(true);

  const onCancel = (): void => setDialogOpen(false);

  const onConfirm = (): void => {
    submitForm();
  };

  const disabled = or(!isValid, !dirty);

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelSave) as string}
        disabled={disabled as boolean}
        title={t(labelSave) as string}
        onClick={onClick}
      >
        <SaveIcon
          className={classes.icon}
          color={disabled ? 'disabled' : 'primary'}
        />
      </IconButton>
      <ConfirmDialog
        confirmDisabled={isSubmitting}
        dataTestId={{
          dataTestIdCanceledButton: labelCancel,
          dataTestIdConfirmButton: labelConfirm
        }}
        labelMessage={t(labelConfirm)}
        labelTitle={t(labelDoYouWantToConfirmAction)}
        open={dialogOpen}
        submitting={isSubmitting}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default SaveAction;
