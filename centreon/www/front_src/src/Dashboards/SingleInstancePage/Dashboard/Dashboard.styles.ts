import { makeStyles } from 'tss-react/mui';

export const useDashboardStyles = makeStyles()((theme) => ({
  body: {
    marginTop: theme.spacing(1.5)
  },
  divider: {
    borderStyle: 'dashed'
  },
  editActions: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  }
}));
