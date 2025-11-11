import { ChangeEvent, ReactElement } from 'react';

import { Switch } from '@centreon/ui/components';
import { FormControlLabel } from '@mui/material';
import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { Command } from '../../models';
import { labelEnableShellSyntax } from '../../translatedLabels';

const EnableShellSyntax = (): ReactElement => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<Command>();

  const value = values?.isShellEnabled;

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched('isShellEnabled', true);
    setFieldValue('isShellEnabled', event.target.checked);
  };

  return (
    <FormControlLabel
      control={
        <Switch
          size="small"
          color="success"
          checked={value}
          onChange={change}
        />
      }
      label={t(labelEnableShellSyntax)}
      labelPlacement="start"
      sx={{
        marginLeft: 0,
        '& .MuiFormControlLabel-label': {
          marginRight: 2
        }
      }}
    />
  );
};

export default EnableShellSyntax;
