import { makeStyles } from 'tss-react/mui';

export const useStatusGridCondensedStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  status: {
    borderRadius: theme.shape.borderRadius,
    height: theme.spacing(12)
  },
  statuses: {
    display: 'grid',
    gap: theme.spacing(1),
    gridTemplateColumns: 'repeat(auto-fit, minmax(140px, auto))',
    width: '100%'
  }
}));
