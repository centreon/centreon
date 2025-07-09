import { makeStyles } from 'tss-react/mui';

export const useDashboardStyles = makeStyles()((theme) => ({
  divider: {
    borderStyle: 'dashed'
  },
  editActions: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  },
  body: {
    marginTop: theme.spacing(1.5)
  }
}));
