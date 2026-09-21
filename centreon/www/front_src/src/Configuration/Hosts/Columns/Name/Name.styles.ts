import { makeStyles } from 'tss-react/mui';

export const useNameStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(0.5)
  },
  icon: {
    flexShrink: 0,
    height: theme.spacing(2),
    width: theme.spacing(2)
  }
}));
