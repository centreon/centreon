<<<<<<< HEAD
import { useTheme } from '@mui/material';
import IconAcknowledge from '@mui/icons-material/Person';
=======
import * as React from 'react';

import { useTheme } from '@material-ui/core';
import IconAcknowledge from '@material-ui/icons/Person';
>>>>>>> centreon/dev-21.10.x

import Chip from '.';

const AcknowledgeChip = (): JSX.Element => {
  const theme = useTheme();

  return (
    <Chip
      color={theme.palette.action.acknowledged}
      icon={<IconAcknowledge fontSize="small" />}
    />
  );
};

export default AcknowledgeChip;
