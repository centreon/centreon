import { useTranslation } from 'react-i18next';

import { ComponentColumnProps } from '@centreon/ui';
import { Switch } from '@centreon/ui/components';
import { Tooltip } from '@mui/material';

import useStyles from './Status.styles';
import useStatus from './useStatus';

import {
  labelDisabled,
  labelEnableDisable,
  labelEnabled
} from '../../../translatedLabels';

const Status = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const { change, checked } = useStatus({ row });

  return (
    <Tooltip title={checked ? t(labelEnabled) : t(labelDisabled)}>
      <Switch
        aria-label={t(labelEnableDisable)}
        data-testid={`${labelEnableDisable}_${row.id}`}
        checked={checked}
        className={classes.switch}
        color="primary"
        size="small"
        onClick={change}
      />
    </Tooltip>
  );
};

export default Status;
