// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Tooltip } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';
import { Switch } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { configurationAtom } from '../../../atoms';
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

  const configuration = useAtomValue(configurationAtom);
  const actions = configuration?.actions;

  // A module that declares `enableDisable` owns this column, so the toggle stays
  // on screen when the predicate says no — disabled, which is what a read-only
  // user is meant to see. A module that declares nothing gets no column at all.
  if (!actions?.enableDisable) {
    return;
  }

  const canChange = actions.enableDisable(row);

  return (
    <Tooltip title={checked ? t(labelEnabled) : t(labelDisabled)}>
      <Switch
        aria-label={t(labelEnableDisable)}
        checked={checked}
        className={classes.switch}
        color="primary"
        data-testid={`${labelEnableDisable}_${row.id}`}
        disabled={isMutating || !canChange}
        onClick={change}
        size="small"
      />
    </Tooltip>
  );
};

export default Status;
