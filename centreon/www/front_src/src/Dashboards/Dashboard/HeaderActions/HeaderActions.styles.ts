import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  headerActions: {
    display: 'flex',
    flexDirection: 'row-reverse',
    justifyContent: 'space-between',
    marginBottom: theme.spacing(1)
  }
}));
