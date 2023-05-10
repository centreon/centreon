import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  header: {
    alignItems: 'baseline',
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    display: 'flex',
    flexDirection: 'row',

    h1: {
      font: 'normal normal 600 24px/24px Roboto',
      letterSpacing: '0.18px',
      margin: '0 0 12px 0'
    },
    justifyContent: 'space-between',
    marginBottom: '20px',

    nav: {
      display: 'flex',
      gap: '20px',
      justifyContent: 'flex-end'
    },

    padding: '0 0 12px 0'
  }
}));

export { useStyles };
