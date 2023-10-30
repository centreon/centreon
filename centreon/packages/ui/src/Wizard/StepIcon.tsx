import { makeStyles } from 'tss-react/mui';

import { StepIconProps } from '@mui/material';
import Avatar from '@mui/material/Avatar';
import Check from '@mui/icons-material/Check';

const useStepIconStyles = makeStyles()((theme) => ({
  avatar: {
    fontSize: theme.typography.body2.fontSize,
    height: 20,
    width: 20
  },
  avatarActive: {
    backgroundColor: theme.palette.primary.main
  },
  avatarCompleted: {
    backgroundColor: theme.palette.primary.main
  },
  completed: {
    fontSize: theme.typography.body2.fontSize,
    zIndex: 1
  },
  root: {
    height: 22
  }
}));

const StepIcon = ({ active, completed, icon }: StepIconProps): JSX.Element => {
  const { classes } = useStepIconStyles();

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
