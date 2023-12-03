import { useState } from 'react';

import { isNil } from 'ramda';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { Switch } from './useColumnStyles';

const ActivatePublicLink = ({ row }: ComponentColumnProps): JSX.Element => {
  const [checked, setChecked] = useState(row?.isPublic);

  const isNestedRow = !isNil(row?.role);

  const activatePublicLink = (): void => {
    setChecked(true);
  };

  if (!isNestedRow) {
    return (
      <Switch
        checked={checked}
        color="success"
        size="small"
        onClick={activatePublicLink}
      />
    );
  }

  return <Box />;
};

export default ActivatePublicLink;
