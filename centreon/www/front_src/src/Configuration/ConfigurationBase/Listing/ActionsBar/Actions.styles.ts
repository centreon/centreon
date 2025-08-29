import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles<{ hasWriteAccess?: boolean }>()(
  (theme, { hasWriteAccess }) => ({
    bar: {
      display: 'flex'
    },
    actions: {
      display: 'flex',
      gap: theme.spacing(1.5)
    },
    searchBar: {
      width: '100%',
      paddingInline: hasWriteAccess ? theme.spacing(1) : 0,
      display: 'flex',
      alignItems: 'center',
      justifyContent: hasWriteAccess ? 'center' : 'start'
    },
    moreActions: {
      [theme.breakpoints.down('md')]: {
        display: 'none'
      }
    },
    ActionsList: {
      width: theme.spacing(19)
    }
  })
);
