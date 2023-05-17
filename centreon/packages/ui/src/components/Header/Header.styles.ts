import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  divider: {
    borderColor: theme.palette.primary.main,
    marginBottom: theme.spacing(2.5)
  },
  header: {
    alignItems: 'baseline',
    display: 'flex',
    flexDirection: 'row',

    justifyContent: 'space-between',
    marginBottom: theme.spacing(2.5),

    nav: {
      display: 'flex',
      gap: theme.spacing(2.5),
      justifyContent: 'flex-end'
    }
  }
}));

export { useStyles };
