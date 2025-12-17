import { makeStyles } from 'tss-react/mui';

export const useListStyles = makeStyles()((theme) => ({
  item: {
    '&[data-isRemoved="true"]': {
      '& .MuiAvatar-root,.MuiListItemText-root,[data-type="group-chip"]': {
        filter: 'opacity(0.5)'
      }
    }
  },
  itemNameAndGroup: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(3),
    width: '100%'
  },
  list: {
    borderBottom: `1px solid ${theme.palette.divider}`,
    borderTop: `1px solid ${theme.palette.divider}`,
    maxHeight: '35vw',
    overflowY: 'auto',
    width: '100%'
  },
  name: {
    flexGrow: 0,
    maxWidth: '200px',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  },
  stateChip: {
    '&[data-state="added"]': {
      borderColor: theme.palette.success.main,
      color: theme.palette.success.main
    },
    '&[data-state="removed"]': {
      borderColor: theme.palette.error.main,
      color: theme.palette.error.main
    },
    '&[data-state="updated"]': {
      borderColor: theme.palette.info.main,
      color: theme.palette.info.main
    }
  }
}));
