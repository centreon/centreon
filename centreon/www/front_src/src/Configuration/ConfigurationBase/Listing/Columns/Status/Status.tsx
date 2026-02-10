import { Tooltip } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';
import { Switch } from '@centreon/ui/components';

import { useTranslation } from 'react-i18next';

import {
  labelDisabled,
  labelEnableDisable,
  labelEnabled
} from '../../../translatedLabels';
import useStyles from './Status.styles';
import useStatus from './useStatus';

const Status = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const { isMutating, change, checked } = useStatus({ row });

  return (
    <Tooltip title={checked ? t(labelEnabled) : t(labelDisabled)}>
      <Switch
        aria-label={t(labelEnableDisable)}
        checked={checked}
        className={classes.switch}
        color="primary"
        data-testid={`${labelEnableDisable}_${row.id}`}
        disabled={isMutating}
        onClick={change}
        size="small"
      />
    </Tooltip>
  );
};

export default Status;
