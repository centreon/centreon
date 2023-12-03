import { useState } from 'react';

import { equals, isNil } from 'ramda';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { Role as RoleType } from '../models';

import { Switch, useColumnStyles } from './useColumnStyles';

const ActivatePublicLink = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles();

  const { isPublic, role, ownRole } = row;

  const [checked, setChecked] = useState(isPublic);

  const isNestedRow = !isNil(role);

  const activatePublicLink = (): void => {
    setChecked(true);
  };

  if (isNestedRow) {
    return <Box />;
  }

  if (equals(ownRole, RoleType.Viewer)) {
    return <Box className={classes.line}>-</Box>;
  }

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
