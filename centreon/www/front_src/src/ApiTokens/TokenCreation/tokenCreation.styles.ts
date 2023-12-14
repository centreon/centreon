import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  button: {
    height: theme.spacing(4)
  },
  container: {
    display: 'flex',
    justifyContent: 'end'
  },
  invisible: {
    opacity: 0
  }
}));
