import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  condensed: {
    justifyContent: 'space-evenly'
  },
  container: {
    display: 'flex',
    justifyContent: 'space-between',
    marginBottom: theme.spacing(2),
    width: '100%'
  }
}));
