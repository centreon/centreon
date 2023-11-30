import { useState } from 'react';

import { styled } from '@mui/material/styles';
import { Switch as MUISwitch } from '@mui/material';

import { ComponentColumnProps } from 'packages/ui/src';

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

const ActivatePublicLink = ({ row }: ComponentColumnProps): JSX.Element => {
  const [checked, setChecked] = useState(row?.isPublic);

  const activatePublicLink = (): void => {
    setChecked(true);
  };

  return (
    <Switch
      checked={checked}
      color="success"
      size="small"
      onClick={activatePublicLink}
    />
  );
};

export default ActivatePublicLink;
