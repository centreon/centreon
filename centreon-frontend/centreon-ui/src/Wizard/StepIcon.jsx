import React from 'react';
import PropTypes from 'prop-types';
import classNames from 'clsx';
import { makeStyles } from '@material-ui/core/styles';
import Avatar from '@material-ui/core/Avatar';
import Check from '@material-ui/icons/Check';

const useStepIconStyles = makeStyles((theme) => ({
  root: {
    color: '#000000',
    display: 'flex',
    height: 22,
    alignItems: 'center',
  },
  avatar: {
    width: 20,
    height: 20,
    color: '#000000',
    backgroundColor: '#ffffff',
    fontSize: '0.8rem',
  },
  avatarActive: {
    color: '#ffffff',
    backgroundColor: theme.palette.primary.main,
    boxShadow: '0 1px 2px 1px rgba(0,0,0,.25)',
  },
  avatarCompleted: {
    backgroundColor: theme.palette.primary.main,
  },
  completed: {
    color: '#ffffff',
    zIndex: 1,
    fontSize: 18,
  },
}));

const StepIcon = ({ active, completed, icon }) => {
  const classes = useStepIconStyles();

  return (
    <div className={classes.root}>
      {completed ? (
        <Avatar className={`${classes.avatar} ${classes.avatarCompleted}`}>
          <Check className={classes.completed} />
        </Avatar>
      ) : (
        <Avatar
          className={classNames(classes.avatar, {
            [classes.avatarActive]: active,
          })}
        >
          {icon}
        </Avatar>
      )}
    </div>
  );
};

StepIcon.propTypes = {
  active: PropTypes.bool,
  completed: PropTypes.bool,
  icon: PropTypes.node,
};

StepIcon.defaultProps = {
  active: false,
  completed: false,
  icon: null,
};

export default StepIcon;
