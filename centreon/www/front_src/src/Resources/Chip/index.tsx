<<<<<<< HEAD
import { Box } from '@mui/material';
=======
import * as React from 'react';

import { makeStyles, Theme } from '@material-ui/core';
import { CreateCSSProperties } from '@material-ui/core/styles/withStyles';

const useStyles = makeStyles<Theme, { color?: string }>((theme) => ({
  chip: ({ color }): CreateCSSProperties => ({
    height: theme.spacing(2.5),
    width: theme.spacing(2.5),
    ...(color && {
      color,
    }),
  }),
}));
>>>>>>> centreon/dev-21.10.x

interface Props {
  color?: string;
  icon: JSX.Element;
}

const Chip = ({ icon, color }: Props): JSX.Element => {
<<<<<<< HEAD
  return (
    <Box
      sx={{
        ...(color && {
          color,
        }),
      }}
    >
      {icon}
    </Box>
  );
=======
  const classes = useStyles({ color });

  return <div className={`${classes.chip}`}>{icon}</div>;
>>>>>>> centreon/dev-21.10.x
};

export default Chip;
