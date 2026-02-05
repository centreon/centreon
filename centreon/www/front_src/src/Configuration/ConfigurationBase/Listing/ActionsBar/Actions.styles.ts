import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles<{ hasWriteAccess?: boolean }>()(
  (theme, { hasWriteAccess }) => ({
    ActionsList: {
      width: theme.spacing(19)
    },
    actions: {
      display: 'flex',
      gap: theme.spacing(1.5)
    },
    bar: {
      display: 'flex'
    },
    moreActions: {
      [theme.breakpoints.down('md')]: {
        display: 'none'
      }
    },
    searchBar: {
      alignItems: 'center',
      display: 'flex',
      justifyContent: hasWriteAccess ? 'center' : 'start',
      paddingInline: hasWriteAccess ? theme.spacing(1) : 0,
      width: '100%'
    }
  })
);
