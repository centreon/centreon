<<<<<<< HEAD
import { useTheme } from '@mui/material';
import IconEvent from '@mui/icons-material/Event';
=======
import * as React from 'react';

import { useTheme } from '@material-ui/core';
import IconEvent from '@material-ui/icons/Event';
>>>>>>> centreon/dev-21.10.x

import Chip from '../../../../Chip';

const EventChip = (): JSX.Element => {
  const theme = useTheme();

  return <Chip color={theme.palette.primary.main} icon={<IconEvent />} />;
};

export default EventChip;
