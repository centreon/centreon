import { useState } from 'react';

import { styled } from '@mui/material/styles';
import { Switch as MUISwitch } from '@mui/material';

import {
  Method,
  type ComponentColumnProps,
  useMutationQuery,
  ResponseError
} from '@centreon/ui';

import { notificationtEndpoint } from '../../../EditPanel/api/endpoints';

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

const ActivateAction = ({ row }: ComponentColumnProps): JSX.Element => {
  const [checked, setChecked] = useState(row?.isActivated);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => notificationtEndpoint({ id: row.id }),
    method: Method.PATCH
  });

  const onClick = (event): void => {
    const value = event.target.checked;
    setChecked(value);

    mutateAsync({ is_activated: value }).then((response) => {
      if ((response as ResponseError).isError) {
        setChecked(!value);
      }
    });
  };

  return (
    <Switch checked={checked} color="success" size="small" onClick={onClick} />
  );
};

export default ActivateAction;
