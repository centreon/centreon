import { useEffect, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';

import { styled } from '@mui/material/styles';
import { Switch as MUISwitch } from '@mui/material';

import { ComponentColumnProps, Method, useMutationQuery } from '@centreon/ui';

import { resourceAccessRuleEndpoint } from '../../AddEditResourceAccessRule/api/endpoints';

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
    <Switch checked={checked} color="success" size="small" onClick={onClick} />
  );
};

export default Activate;
