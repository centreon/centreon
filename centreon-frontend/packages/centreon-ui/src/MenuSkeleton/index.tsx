import * as React from 'react';

import { useTheme, makeStyles, fade } from '@material-ui/core';
import { Skeleton } from '@material-ui/lab';

const useStyles = makeStyles((theme) => ({
  skeleton: {
    backgroundColor: fade(theme.palette.grey[50], 0.4),
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
    <Skeleton
      animation={animate ? 'wave' : false}
      className={classes.skeleton}
      height={theme.spacing(5)}
      width={theme.spacing(width)}
    />
  );
};

export default MenuLoader;
