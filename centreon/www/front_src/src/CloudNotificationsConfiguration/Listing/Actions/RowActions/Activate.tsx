import { useState } from 'react';

import { styled } from '@mui/material/styles';
import { Switch as MUISwitch } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

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
  const [checked, setchecked] = useState(row?.isActivated);

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    setchecked(event.target.checked);
  };

  return (
    <Switch
      checked={checked}
      color="success"
      size="small"
      onChange={handleChange}
    />
  );
};

export default ActivateAction;
