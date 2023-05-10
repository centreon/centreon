import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2),
    justifyContent: 'flex-end'
  },
  dashboardForm: {
    display: 'flex',
    flexDirection: 'column',

    h2: {
      color: theme.palette.primary.main,
      font: 'normal normal 600 24px/24px Roboto',
      letterSpacing: '0.18px',

      margin: '0 0 12px 0'
    },
    maxWidth: '480px',

    width: '100%'
  }
}));

export { useStyles };
