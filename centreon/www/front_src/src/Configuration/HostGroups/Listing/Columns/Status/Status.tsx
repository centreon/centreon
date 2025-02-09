import { useTranslation } from 'react-i18next';

import { Tooltip } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { Switch } from '@centreon/ui/components';
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
        data-tesid={`${labelEnableDisable}_${row.id}`}
        checked={checked}
        className={classes.switch}
        color="primary"
        size="small"
        onClick={change}
        disabled={isMutating}
      />
    </Tooltip>
  );
};

export default Status;
