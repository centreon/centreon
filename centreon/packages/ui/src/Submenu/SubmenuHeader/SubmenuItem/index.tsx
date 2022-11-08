import * as React from 'react';

import clsx from 'clsx';

import { makeStyles, Typography } from '@material-ui/core';

const useStyles = makeStyles((theme) => ({
  blue: {
    backgroundColor: '#2ad1d4',
  },
  count: {
    color: theme.palette.background.paper,
    float: 'right',
    fontSize: '.8rem',
    padding: '10px',
    position: 'absolute',
    right: '0',
    textDecoration: 'none',
  },
  dot: {
    borderRadius: '50%',
    height: '8px',
    left: '0',
    position: 'absolute',
    top: '45%',
    transform: 'translateY(-50%)',
    width: '8px',
  },
  dotted: {
    paddingLeft: '22px',
  },
  gray: {
    backgroundColor: '#818185',
  },
  green: {
    backgroundColor: '#87bd23',
  },
  orange: {
    backgroundColor: '#ffa125',
  },
  red: {
    backgroundColor: '#ed1b23',
  },
  submenuItem: {
    display: 'block',
    float: 'left',
    position: 'relative',
    width: '100%',
  },
  title: {
    color: theme.palette.background.paper,
    display: 'block',
    float: 'left',
    fontSize: '.8rem',
    padding: '10px',
    paddingLeft: '10px',
    position: 'relative',
    textDecoration: 'none',
  },
  titleContent: {
    marginLeft: theme.spacing(1),
  },
}));

interface Props {
  countTestId?: string;
  dotColored?: string;
  submenuCount: string | number;
  submenuTitle: string;
  titleTestId?: string;
}

const SubmenuItem = ({
  dotColored,
  submenuTitle,
  submenuCount,
  titleTestId,
  countTestId,
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <li className={classes.submenuItem}>
      <span
        className={clsx(classes.title, {
          [classes.dotted]: !!dotColored,
        })}
        data-testid={titleTestId}
      >
        <span className={clsx(classes.dot, classes[dotColored || ''])} />
        <Typography className={classes.titleContent} variant="body2">
          {submenuTitle}
        </Typography>
      </span>
      <span className={classes.count} data-testid={countTestId}>
        <Typography variant="body2">{submenuCount}</Typography>
      </span>
    </li>
  );
};

export default SubmenuItem;
