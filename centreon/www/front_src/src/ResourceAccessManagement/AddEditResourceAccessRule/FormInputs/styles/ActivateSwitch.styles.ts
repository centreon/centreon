import { makeStyles } from 'tss-react/mui';

export const useActivateSwitchStyles = makeStyles()((theme) => ({
  switch: {
    '& .MuiSwitch-switchBase': {
      '&.Mui-checked': {
        '& + .MuiSwitch-track': {
          backgroundColor: theme.palette.success.main,
          opacity: 1
        },
        color: theme.palette.common.white
      }
    },
    '& .MuiSwitch-thumb': {
      backgroundColor: theme.palette.common.white
    }
  }
}));
