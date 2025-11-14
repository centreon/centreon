import { ChangeEvent, ReactElement } from 'react';

import { Switch, Tooltip } from '@centreon/ui/components';
import { FormControlLabel } from '@mui/material';
import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { Command } from '../../models';
import {
  labelEnableShellSyntax,
  labelEnableShellSyntaxTooltip
} from '../../translatedLabels';

import HelpOutlineIcon from '@mui/icons-material/HelpOutline';

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
    <div className="flex items-center gap-8">
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
      <Tooltip label={t(labelEnableShellSyntaxTooltip)}>
        <HelpOutlineIcon fontSize="small" color="primary" />
      </Tooltip>
    </div>
  );
};

export default EnableShellSyntax;
