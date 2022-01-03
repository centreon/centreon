import * as React from 'react';

import { useTheme, alpha } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

import LoadingSkeleton from '../LoadingSkeleton';

const useStyles = makeStyles((theme) => ({
  skeleton: {
    backgroundColor: alpha(theme.palette.grey[50], 0.4),
    margin: theme.spacing(0.5, 2, 1, 2),
  },
}));

interface Props {
  animate?: boolean;
  width?: number;
}

const MenuLoader = ({ width = 15, animate = true }: Props): JSX.Element => {
  const theme = useTheme();
  const classes = useStyles();

  return (
    <LoadingSkeleton
      animation={animate ? 'wave' : false}
      className={classes.skeleton}
      height={theme.spacing(5)}
      variant="text"
      width={theme.spacing(width)}
    />
  );
};

export default MenuLoader;
