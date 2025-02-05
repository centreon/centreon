import { useEffect, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Switch, Tooltip } from '@mui/material';

import { ComponentColumnProps, Method, useMutationQuery } from '@centreon/ui';

import {
  labelDisabled,
  labelEnableDisable,
  labelEnabled
} from '../../../translatedLabels';

import { getHostGroupEndpoint } from '../../../api/endpoints';
import useStyles from './Status.styles';

const Status = ({ row }: ComponentColumnProps): JSX.Element => {
  const queryClient = useQueryClient();

  const { t } = useTranslation();
  const { classes } = useStyles();

  const [checked, setChecked] = useState(row?.is_activated);

  useEffect(() => {
    if (row?.is_activated !== checked) {
      setChecked(row?.is_activated);
    }
  }, [row?.is_activated]);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => getHostGroupEndpoint({ id: row.id }),
    method: Method.PATCH,
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ['resource-access-rules'] })
  });

  const onClick = (e: React.BaseSyntheticEvent): void => {
    const value = e.target.checked;
    setChecked(value);

    mutateAsync({
      payload: { is_enabled: value }
    });
  };

  return (
    <Tooltip title={checked ? t(labelEnabled) : t(labelDisabled)}>
      <Switch
        aria-label={t(labelEnableDisable)}
        checked={checked}
        className={classes.switch}
        color="success"
        size="small"
        onClick={onClick}
      />
    </Tooltip>
  );
};

export default Status;
