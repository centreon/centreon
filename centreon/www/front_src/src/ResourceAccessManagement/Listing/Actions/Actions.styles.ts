import { makeStyles } from 'tss-react/mui';

const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1.5),
    justifyContent: 'space-between'
  },
  duplicateIcon: {
    color: theme.palette.primary.main,
    fontSize: theme.spacing(2.25)
  },
  icon: {
    '&:hover': {
      color: theme.palette.error.main
    },
    color: theme.palette.primary.main,
    fontSize: theme.spacing(2.5)
  }
}));

export default useActionsStyles;
