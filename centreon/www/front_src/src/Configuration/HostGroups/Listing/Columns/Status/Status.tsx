import { useEffect, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Tooltip } from '@mui/material';

import {
  ComponentColumnProps,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { Switch } from '@centreon/ui/components';
import {
  bulkDisableHostGroupEndpoint,
  bulkEnableHostGroupEndpoint
} from '../../../api/endpoints';
import {
  labelDisabled,
  labelEnableDisable,
  labelEnabled,
  labelHostGroupDisabled,
  labelHostGroupEnabled
} from '../../../translatedLabels';
import useStyles from './Status.styles';

const Status = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const queryClient = useQueryClient();
  const { showSuccessMessage } = useSnackbar();

  const [checked, setChecked] = useState(row?.is_activated);

  useEffect(() => {
    if (row?.is_activated !== checked) {
      setChecked(row?.is_activated);
    }
  }, [row?.is_activated]);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      checked ? bulkDisableHostGroupEndpoint : bulkEnableHostGroupEndpoint,
    method: Method.POST,
    onSuccess: () => {
      showSuccessMessage(
        t(checked ? labelHostGroupDisabled : labelHostGroupEnabled)
      );
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    },
    onError: () => setChecked(!checked)
  });

  const onClick = (e: React.BaseSyntheticEvent): void => {
    const value = e.target.checked;
    setChecked(value);

    mutateAsync({
      payload: { ids: [row.id] }
    });
  };

  return (
    <Tooltip title={checked ? t(labelEnabled) : t(labelDisabled)}>
      <Switch
        aria-label={t(labelEnableDisable)}
        data-tesid={`${labelEnableDisable}_${row.id}`}
        checked={checked}
        className={classes.switch}
        color="primary"
        size="small"
        onClick={onClick}
      />
    </Tooltip>
  );
};

export default Status;
