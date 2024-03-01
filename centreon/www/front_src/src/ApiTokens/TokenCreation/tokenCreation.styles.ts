import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    justifyContent: 'end'
  },
  containerTitle: {
    width: theme.spacing(56)
  },
  input: {
    marginBottom: theme.spacing(2.5),
    width: theme.spacing(56)
  },
  invisible: {
    height: 0,
    opacity: 0,
    width: 0
  },
  root: {
    padding: theme.spacing(0.5, 0.75)
  },
  startIcon: {
    marginLeft: 0,
    marginRight: theme.spacing(0.25)
  },
  title: {
    color: theme.palette.primary.main
  }
}));
