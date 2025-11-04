import { Switch } from '@centreon/ui/components';
import { FormControlLabel } from '@mui/material';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';
import { labelEnableShellSyntax } from '../../translatedLabels';

const EnableShellSyntax = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <FormControlLabel
      control={
        <Switch
          size="small"
          color="success"
          checked={true}
          onChange={() => undefined}
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
