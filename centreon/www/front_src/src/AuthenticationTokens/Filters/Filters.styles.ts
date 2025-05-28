import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    background: theme.palette.background.paper,
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1.5),
    marginTop: theme.spacing(1),
    padding: theme.spacing(2),
    width: theme.spacing(44)
  },
  helperText: {
    textAlign: 'center'
  },
  popoverMenu: {
    zIndex: theme.zIndex.modal
  },
  badge: {
    '& .MuiBadge-badge': {
      fontSize: theme.typography.caption.fontSize,
      height: theme.spacing(1.75),
      minWidth: theme.spacing(1.75),
      padding: theme.spacing(0, 0.5)
    }
  },
  additionalFiltersButtons: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: theme.spacing(2)
  },
  statusFilter: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingInlineStart: theme.spacing(1)
  },
  statusFilterName: {
    fontWeight: theme.typography.fontWeightMedium
  },
  filters: {
    maxWidth: theme.spacing(60),
    minWidth: theme.spacing(20),
    width: '100%'
  }
}));
