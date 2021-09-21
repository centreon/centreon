import React from 'react';

import clsx from 'clsx';

import { makeStyles, Theme, Typography } from '@material-ui/core';

const colors = {
  blue: '#2ad1d4',
  'gray-dark': '#818185',
  'gray-light': '#cdcdcd',
  green: '#87bd23',
  orange: '#ff9913',
  red: '#ed1c24',
};

interface StylesProps {
  iconColor: string;
}

const useStyles = makeStyles<Theme, StylesProps>((theme) => ({
  bordered: {
    border: ({ iconColor }): string => `2px solid ${colors[iconColor]}`,
  },
  colored: {
    background: ({ iconColor }): string => colors[iconColor],
    border: '2px solid transparent',
  },
  icon: {
    borderRadius: '50px',
    boxSizing: 'border-box',
    color: theme.palette.background.paper,
    cursor: 'pointer',
    display: 'inline-block',
    fontSize: '.875rem',
    height: '32px',
    margin: '0 6px',
    minWidth: '32px',
    overflow: 'hidden',
    padding: '0px 5px',
    position: 'relative',
    textAlign: 'center',
    textDecoration: 'none',
  },
  numberCount: {
    color: theme.palette.background.paper,
    lineHeight: '28px',
  },
  numberWrap: {
    fontSize: '0.875rem',
    position: 'relative',
    textDecoration: 'none',
  },
}));

interface Props {
  iconColor: string;
  iconNumber: number | JSX.Element;
  iconType: string;
}

const IconNumber = ({
  iconColor,
  iconType,
  iconNumber,
}: Props): JSX.Element => {
  const classes = useStyles({ iconColor });

  return (
    <span className={clsx(classes.icon, classes[iconType], classes.numberWrap)}>
      <span className={classes.numberCount}>
        <Typography style={{ lineHeight: 'unset' }}>{iconNumber}</Typography>
      </span>
    </span>
  );
};

export default IconNumber;
