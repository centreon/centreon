import * as React from 'react';

import clsx from 'clsx';

import { makeStyles } from '@material-ui/core';

interface Props {
  active: boolean;
  children: React.ReactChildren | Array<React.ReactElement>;
  submenuType: string;
}

const useStyles = makeStyles({
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
    padding: '6px 6px 6px 16px',
    position: 'relative',
  },
});

const SubmenuHeader = ({
  submenuType,
  children,
  active,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <div
      className={clsx(classes[submenuType], {
        [classes.active]: active,
      })}
      {...props}
    >
      {children}
    </div>
  );
};

export default SubmenuHeader;
