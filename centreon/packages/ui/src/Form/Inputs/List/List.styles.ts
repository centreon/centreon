import { makeStyles } from 'tss-react/mui';

export const useListStyles = makeStyles()((theme) => ({
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
  innerContent: {
    flexGrow: 1
  },
  items: {
    maxHeight: theme.spacing(16),
    overflowY: 'auto'
  },
  list: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  }
}));
