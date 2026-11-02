import { makeStyles } from 'tss-react/mui';

export const defaultFiltersPanelWidth = 42;

export const useFilterStyles = makeStyles()((theme) => ({
  badge: {
    '& .MuiBadge-badge': {
      fontSize: theme.typography.caption.fontSize,
      height: theme.spacing(1.75),
      minWidth: theme.spacing(1.75),
      padding: theme.spacing(0, 0.5)
    }
  },
  filters: {
    maxWidth: theme.spacing(60),
    minWidth: theme.spacing(20),
    width: '100%'
  },
  statusFilter: {
    alignItems: 'start',
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'space-between',
    paddingInlineStart: theme.spacing(1)
  },
  statusFilterName: {
    fontWeight: theme.typography.fontWeightMedium
  }
}));
