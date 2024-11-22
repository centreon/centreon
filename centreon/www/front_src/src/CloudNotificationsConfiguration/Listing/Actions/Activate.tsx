import { useEffect, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';

import { Switch as MUISwitch } from '@mui/material';
import { styled } from '@mui/material/styles';

import {
  type ComponentColumnProps,
  Method,
  ResponseError,
  useMutationQuery
} from '@centreon/ui';

import { notificationEndpoint } from '../../Panel/api/endpoints';

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

  const [checked, setChecked] = useState(row?.isActivated);

  useEffect(() => {
    if (row?.isActivated !== checked) {
      setChecked(row?.isActivated);
    }
  }, [row?.isActivated]);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => notificationEndpoint({ id: row.id }),
    method: Method.PATCH
  });

  const onClick = (event): void => {
    const value = event.target.checked;
    setChecked(value);

    mutateAsync({
      payload: { is_activated: value }
    }).then((response) => {
      if ((response as ResponseError).isError) {
        setChecked(!value);

        return;
      }
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    });
  };

  return (
    <Switch checked={checked} color="success" size="small" onClick={onClick} />
  );
};

export default Activate;
