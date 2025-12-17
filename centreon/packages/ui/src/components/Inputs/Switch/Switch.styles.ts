import { makeStyles } from 'tss-react/mui';

export const useSwitchStyles = makeStyles()((theme) => ({
  switch: {
    '& .MuiSwitch-thumb': {
      backgroundColor: theme.palette.common.white
    },
    '& span[data-checked="true"] + .MuiSwitch-track': {
      backgroundColor: theme.palette.success.main,
      opacity: 1
    }
  }
}));
