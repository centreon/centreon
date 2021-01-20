import * as React from 'react';

import { Typography, makeStyles } from '@material-ui/core';

const useStyles = makeStyles((theme) => ({
  option: {
    fontSize: theme.typography.pxToRem(14),
  },
}));

interface Props {
  children: string;
}

const Option = ({ children }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Typography variant="body1" className={classes.option}>
      {children}
    </Typography>
  );
};

export default Option;
