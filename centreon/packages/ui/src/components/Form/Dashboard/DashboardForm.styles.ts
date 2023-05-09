import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  dashboardForm: {
    display: 'flex',
    flexDirection: 'column',

    width: '100%',
    maxWidth: '480px',

    h2: {
      font: 'normal normal 600 24px/24px Roboto',
      letterSpacing: '0.18px',
      margin: '0 0 12px 0',

      color: theme.palette.primary.main,
    },

  },
  actions: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: theme.spacing(2),
  }

}));

export { useStyles };
