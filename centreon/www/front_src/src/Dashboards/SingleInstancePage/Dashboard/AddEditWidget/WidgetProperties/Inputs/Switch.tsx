import { ChangeEvent, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { Box, FormControlLabel, Typography } from '@mui/material';

import { Switch } from '@centreon/ui/components';

import { Widget, WidgetPropertyProps } from '../../models';
import { useCanEditProperties } from '../../../hooks/useCanEditDashboard';
import Subtitle from '../../../components/Subtitle';

import { getProperty } from './utils';
import { useSwitchStyles } from './Inputs.styles';

const WidgetSwitch = ({
  propertyName,
  label,
  endAdornment,
  secondaryLabel
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useSwitchStyles();

  const { values, setFieldValue } = useFormikContext<Widget>();

  const { canEditField } = useCanEditProperties();

  const value = useMemo<boolean | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const changeSwitchValue = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(`options.${propertyName}`, event.target.checked);
  };

  return (
    <>
      {secondaryLabel && <Subtitle>{t(secondaryLabel)}</Subtitle>}
      <FormControlLabel
        control={
          <Switch
            checked={value}
            inputProps={{
              'aria-label': t(label) || ''
            }}
            onChange={changeSwitchValue}
          />
        }
        disabled={!canEditField}
        label={
          <Box className={classes.switch}>
            <Typography>{t(label)}</Typography>
            {endAdornment}
          </Box>
        }
      />
    </>
  );
};

export default WidgetSwitch;
