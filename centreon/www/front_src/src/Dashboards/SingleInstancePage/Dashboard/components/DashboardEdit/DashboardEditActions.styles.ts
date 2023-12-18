import { makeStyles } from 'tss-react/mui';

export const useDashboardEditActionsStyles = makeStyles()((theme) => ({
  root: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  }
}));
