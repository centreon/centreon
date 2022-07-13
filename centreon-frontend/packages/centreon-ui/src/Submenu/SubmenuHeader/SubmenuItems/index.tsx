import * as React from 'react';

import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles({
  submenuItems: {
    listStyle: 'none',
    margin: 0,
    padding: 0,
  },
});

interface Props {
  children: React.ReactElement | Array<React.ReactElement>;
}

const SubmenuItems = ({ children }: Props): JSX.Element => {
  const classes = useStyles();

  return <ul className={classes.submenuItems}>{children}</ul>;
};

export default SubmenuItems;
