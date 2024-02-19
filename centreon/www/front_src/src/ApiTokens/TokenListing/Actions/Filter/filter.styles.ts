import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  checkbox: {
    paddingLeft: theme.spacing(0.25)
  },
  container: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.default,
    display: 'flex',
    flexDirection: 'column',
    marginTop: theme.spacing(1),
    minWidth: theme.spacing(44)
  },
  input: {
    margin: theme.spacing(2, 0),
    width: theme.spacing(40)
  },
  popper: {
    '& .MuiPaper-root': {
      backgroundColor: theme.palette.background.default
    },
    zIndex: theme.zIndex.tooltip
  },
  root: {
    backgroundClip: 'red'
  }
}));
