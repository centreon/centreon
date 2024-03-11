import { useEffect, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { styled } from '@mui/material/styles';
import { Switch as MUISwitch, Tooltip } from '@mui/material';

import { ComponentColumnProps, Method, useMutationQuery } from '@centreon/ui';

import { resourceAccessRuleEndpoint } from '../../AddEditResourceAccessRule/api/endpoints';
import {
  labelActiveOrInactive,
  labelDisabled,
  labelEnabled
} from '../../translatedLabels';

const Switch = styled(MUISwitch)(({ theme }) => ({
  '& .MuiSwitch-switchBase': {
    '&.Mui-checked': {
      '& + .MuiSwitch-track': {
        backgroundColor: theme.palette.success.main,
        opacity: 1
      },
      color: theme.palette.common.white
    }
  },
  '& .MuiSwitch-thumb': {
    backgroundColor: theme.palette.common.white
  }
}));

const Activate = ({ row }: ComponentColumnProps): JSX.Element => {
  const queryClient = useQueryClient();

  const { t } = useTranslation();

  const [checked, setChecked] = useState(row?.isActivated);

  useEffect(() => {
    if (row?.isActivated !== checked) {
      setChecked(row?.isActivated);
    }
  }, [row?.isActivated]);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => resourceAccessRuleEndpoint({ id: row.id }),
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
        aria-label={t(labelActiveOrInactive)}
        checked={checked}
        color="success"
        size="small"
        onClick={onClick}
      />
    </Tooltip>
  );
};

export default Activate;
