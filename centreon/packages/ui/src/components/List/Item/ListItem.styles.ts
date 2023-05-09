import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  listItem: {
    display: 'flex',
    flexDirection: 'column',

    border: `1px solid ${theme.palette.text.disabled}`,
    borderRadius: '8px',

    minWidth: '280px',
    width: '320px',
    height: '210px',
    padding: '12px',

    h3: {
      font: 'normal normal 600 20px/28px Roboto',
      letterSpacing: '0',
      color: theme.palette.text.primary,

      margin: '0'
    },

    p: {
      font: 'normal normal 400 16px/20px Roboto',
      letterSpacing: '0',
      color: theme.palette.text.secondary,

      margin: '0'
    }
  }

}));

export { useStyles };
