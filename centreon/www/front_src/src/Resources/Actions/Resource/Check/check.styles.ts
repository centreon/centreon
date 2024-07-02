import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  buttonGroup: {
    alignItems: 'center'
  },
  container: {
    '& .MuiButton-root': {
      backgroundColor: 'transparent',
      boxShadow: theme.spacing(0, 0)
    },
    backgroundColor: theme.palette.primary.main
  },
  disabled: {
    backgroundColor: theme.palette.action.disabledBackground
  },
  iconArrow: {
    color: theme.palette.background.paper
  }
}));
