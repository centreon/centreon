import * as React from 'react';

import clsx from 'clsx';

import makeStyles from '@mui/styles/makeStyles';

interface Props {
  active: boolean;
  children: React.ReactChildren | Array<React.ReactElement>;
}

const useStyles = makeStyles((theme) => ({
  active: {
    backgroundColor: '#000915',
  },
  bottom: {
    display: 'flex',
  },
  top: {
    alignItems: 'center',
    backgroundColor: '#232f39',
    display: 'flex',
    flexWrap: 'wrap',
    gridGap: theme.spacing(1),
    padding: theme.spacing(1, 1, 1, 2),
    position: 'relative',
  },
}));

const SubmenuHeader = ({ children, active, ...props }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <div
      className={clsx(classes.top, {
        [classes.active]: active,
      })}
      {...props}
    >
      {children}
    </div>
  );
};

export default SubmenuHeader;
