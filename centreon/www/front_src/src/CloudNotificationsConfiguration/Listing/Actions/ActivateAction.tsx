import { useState } from 'react';

import { Switch as MUISwitch } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

const ActivateAction = ({ row }: ComponentColumnProps): JSX.Element => {
  const [checked, setchecked] = useState(row?.isActivated);

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    setchecked(event.target.checked);
  };

  return (
    <MUISwitch
      checked={checked}
      color="success"
      inputProps={{ 'aria-label': 'controlled' }}
      size="small"
      onChange={handleChange}
    />
  );
};

export default ActivateAction;
