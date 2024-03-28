import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Switch, Tooltip } from '@mui/material';

import {
  labelActiveOrInactive,
  labelDisabled,
  labelEnabled
} from '../../../translatedLabels';
import { useActivateSwitchStyles } from '../styles/ActivateSwitch.styles';

const ActivateSwitch = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActivateSwitchStyles();

  const { setFieldValue, values } = useFormikContext<FormikValues>();

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    const value = event.target.checked;
    setFieldValue('isActivated', value);
  };

  return (
    <FormControlLabel
      control={
        <Tooltip title={t(labelActiveOrInactive)}>
          <Switch
            aria-label={t(labelActiveOrInactive) as string}
            checked={values?.isActivated}
            className={classes.switch}
            color="success"
            name="isActivated"
            size="medium"
            onChange={handleChange}
          />
        </Tooltip>
      }
      label={values?.isActivated ? t(labelEnabled) : t(labelDisabled)}
    />
  );
};

export default ActivateSwitch;
