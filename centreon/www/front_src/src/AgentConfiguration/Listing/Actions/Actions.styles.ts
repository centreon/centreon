import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  search: {
    maxWidth: theme.spacing(60),
    minWidth: theme.spacing(20),
    width: '100%'
  },
  filtersContainer: {
    background: theme.palette.background.paper,
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1.5),
    marginTop: theme.spacing(1),
    padding: theme.spacing(2),
    width: theme.spacing(44)
  },
  clearButton: {
    alignSelf: 'flex-start'
  },
  container: {
    display: 'flex',
    alignItems: 'center'
  },
  searchBar: {
    width: '100%',
    paddingInline: theme.spacing(1),
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center'
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
  }
}));
