import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    gap: theme.spacing(1),
    height: '100%',
    overflow: 'hidden',
    width: '100%'
  },
  flexDirectionColumns: {
    flexDirection: 'column'
  }
}));
