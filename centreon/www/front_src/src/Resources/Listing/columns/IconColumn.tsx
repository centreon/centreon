<<<<<<< HEAD
import { ReactNode } from 'react';

import { makeStyles } from '@mui/styles';

interface Props {
  children: ReactNode;
=======
import * as React from 'react';

import { makeStyles } from '@material-ui/styles';

interface Props {
  children: React.ReactNode;
>>>>>>> centreon/dev-21.10.x
}

const useStyles = makeStyles(() => ({
  column: {
    display: 'flex',
    justifyContent: 'center',
    width: '100%',
  },
}));

const IconColumn = ({ children }: Props): JSX.Element => {
  const classes = useStyles();

  return <div className={classes.column}>{children}</div>;
};

export default IconColumn;
