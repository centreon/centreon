import { makeStyles } from 'tss-react/mui';

export const useTextStyles = makeStyles()((theme) => ({
  critical: {
    color: theme.palette.error.main
  },
  graphText: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    justifyContent: 'center'
  },
  threshold: {
    textAlign: 'center'
  },
  thresholds: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(5),
    whiteSpace: 'nowrap',
    width: '100%'
  },
  warning: {
    color: theme.palette.warning.main
  }
}));
