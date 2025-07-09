import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
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
    },
    marginLeft: theme.spacing(2)
  }
}));

export default useStyles;
