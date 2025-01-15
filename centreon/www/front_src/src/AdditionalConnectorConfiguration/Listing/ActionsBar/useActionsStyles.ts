import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()(() => ({
  actions: {
    alignItems: 'center',
    flexWrap: 'nowrap'
  }
}));

export const useFilterStyles = makeStyles()((theme) => ({
  additionalFilters: {
    background: theme.palette.background.paper,
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1.5),
    marginTop: theme.spacing(1),
    padding: theme.spacing(2),
    width: theme.spacing(42)
  },
  additionalFiltersButtons: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: theme.spacing(2)
  },
  filters: {
    alignItems: 'center',
    display: 'flex',
    width: '80%'
  }
}));
