import { FormikValues, useFormikContext } from 'formik';
import { or } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import SaveIcon from '@mui/icons-material/SaveOutlined';
import { Box, CircularProgress } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { labelSave } from '../../../translatedLabels';

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

  const onConfirm = (): void => {
    submitForm();
  };

  const disabled = or(!isValid, !dirty);

  return (
    <Box>
      {isSubmitting ? (
        <CircularProgress color="primary" size={20} />
      ) : (
        <IconButton
          ariaLabel={t(labelSave) as string}
          disabled={disabled as boolean}
          title={t(labelSave) as string}
          onClick={onConfirm}
        >
          <SaveIcon
            className={classes.icon}
            color={disabled ? 'disabled' : 'primary'}
          />
        </IconButton>
      )}
    </Box>
  );
};

export default SaveAction;
