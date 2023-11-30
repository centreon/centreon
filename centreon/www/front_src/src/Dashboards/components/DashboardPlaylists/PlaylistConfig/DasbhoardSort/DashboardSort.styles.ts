import { makeStyles } from 'tss-react/mui';

export const useDashboardSortStyles = makeStyles()((theme) => ({
  content: {
    '& [data-dragging="false"]': {
      cursor: 'grab'
    },
    '& [data-dragging="true"]': {
      cursor: 'grabbing'
    },
    alignItems: 'center',
    borderBottom: `1px dashed ${theme.palette.action.disabledBackground}`,
    display: 'flex',
    flexDirection: 'row',
    padding: theme.spacing(1, 0)
  },
  items: {
    maxHeight: theme.spacing(16),
    overflowY: 'auto'
  },
  name: {
    flexGrow: 1
  }
}));
