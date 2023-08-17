import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()(() => ({
  dashboardForm: {
    display: 'flex',
    flexDirection: 'column',
    maxWidth: '480px',

    width: '100%'
  }
}));

export { useStyles };
