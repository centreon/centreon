import HelpOutlineIcon from '@mui/icons-material/HelpOutline';
import { FormControlLabel } from '@mui/material';

import { Switch, Tooltip } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { ChangeEvent, ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { Command } from '../../models';
import {
  labelEnableShellSyntax,
  labelEnableShellSyntaxTooltip
} from '../../translatedLabels';
import { useUserPermissions } from '../../useUserPermissions';

const EnableShellSyntax = (): ReactElement => {
  const { t } = useTranslation();

  const { canEdit } = useUserPermissions();

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
            checked={value}
            color="success"
            data-testid="enable-shell-syntax"
            onChange={change}
            size="small"
          />
        }
        disabled={!canEdit || values.isFromMonitoringConnector}
        label={t(labelEnableShellSyntax)}
        labelPlacement="start"
        sx={{
          '& .MuiFormControlLabel-label': {
            marginRight: 2
          },
          marginLeft: 0
        }}
      />
      <Tooltip label={t(labelEnableShellSyntaxTooltip)}>
        <HelpOutlineIcon color="primary" fontSize="small" />
      </Tooltip>
    </div>
  );
};

export default EnableShellSyntax;
