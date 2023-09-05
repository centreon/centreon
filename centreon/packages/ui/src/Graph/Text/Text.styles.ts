import { makeStyles } from 'tss-react/mui';

export const useTextStyles = makeStyles()((theme) => ({
  graphText: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    height: '100%',
    justifyContent: 'center'
  },
  thresholds: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(5)
  }
}));
