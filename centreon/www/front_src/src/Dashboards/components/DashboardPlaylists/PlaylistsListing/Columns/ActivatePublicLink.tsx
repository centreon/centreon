import { useState } from 'react';

import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { Role as RoleType } from '../models';
import { labelPrivatePublic } from '../translatedLabels';

import { Switch, useColumnStyles } from './useColumnStyles';

const ActivatePublicLink = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
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
      aria-label={t(labelPrivatePublic)}
      checked={checked}
      color="success"
      data-testid={labelPrivatePublic}
      size="small"
      onClick={activatePublicLink}
    />
  );
};

export default ActivatePublicLink;
