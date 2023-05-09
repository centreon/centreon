import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  header: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',

    padding: '0 0 12px 0',
    marginBottom: '20px',
    borderBottom: `1px solid ${theme.palette.primary.main}`,

    h1: {
      font: 'normal normal 600 24px/24px Roboto',
      letterSpacing: '0.18px',
      margin: '0 0 12px 0'
    },

    nav: {
      display: 'flex',
      justifyContent: 'flex-end',
      gap: '20px'
    }
  }

}));

export { useStyles };
