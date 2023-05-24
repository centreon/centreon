import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  divider: {
    borderColor: theme.palette.primary.main,
    marginBottom: theme.spacing(2.5)
  },
  header: {
    alignItems: 'flex-start',
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    display: 'flex',
    flexDirection: 'row',
    h1: {
      font: 'normal normal 600 24px/24px Roboto',
      letterSpacing: '0.18px',
      margin: theme.spacing(0, 0, 1.5, 0)
    },
    justifyContent: 'space-between',
    marginBottom: theme.spacing(2.5),

    nav: {
      display: 'flex',
      gap: theme.spacing(1),
      justifyContent: 'flex-end'
    },

    padding: theme.spacing(0, 0, 1.5, 0)
  }
}));

export { useStyles };
