import { Switch as MuiSwitch, SwitchProps } from '@mui/material';

import { useSwitchStyles } from './Switch.styles';

const Switch = ({ checked, ...props }: SwitchProps): JSX.Element => {
  const { classes } = useSwitchStyles();

  return (
    <MuiSwitch
      checked={checked}
      className={classes.switch}
      data-checked={checked}
      {...props}
    />
  );
};

export default Switch;
