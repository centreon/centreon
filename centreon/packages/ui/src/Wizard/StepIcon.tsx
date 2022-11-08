import React from 'react';

import { makeStyles, StepIconProps } from '@material-ui/core';
import Avatar from '@material-ui/core/Avatar';
import Check from '@material-ui/icons/Check';

const useStepIconStyles = makeStyles((theme) => ({
  avatar: {
    backgroundColor: '#ffffff',
    color: '#000000',
    fontSize: '0.8rem',
    height: 20,
    width: 20,
  },
  avatarActive: {
    backgroundColor: theme.palette.primary.main,
    boxShadow: '0 1px 2px 1px rgba(0,0,0,.25)',
    color: '#ffffff',
  },
  avatarCompleted: {
    backgroundColor: theme.palette.primary.main,
  },
  completed: {
    color: '#ffffff',
    fontSize: 18,
    zIndex: 1,
  },
  root: {
    alignItems: 'center',
    color: '#000000',
    display: 'flex',
    height: 22,
  },
}));

const StepIcon = ({ active, completed, icon }: StepIconProps): JSX.Element => {
  const classes = useStepIconStyles();

  return (
    <div className={classes.root}>
      {completed ? (
        <Avatar className={`${classes.avatar} ${classes.avatarCompleted}`}>
          <Check className={classes.completed} />
        </Avatar>
      ) : (
        <Avatar
          className={`${classes.avatar} ${active ? classes.avatarActive : ''}`}
        >
          {icon}
        </Avatar>
      )}
    </div>
  );
};

export default StepIcon;
