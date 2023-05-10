import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  listItem: {
    '& .MuiCardActionArea-root': {
      display: 'flex',
      alignItems: 'flex-start',
      flexDirection: 'column',
      height: '100%',
      justifyContent: 'space-between'
    },
    '& .MuiCardActions-root': {
      display: 'flex',
      flexDirection: 'row-reverse',
      gap: theme.spacing(1),

      opacity: 0
    },

    '&:hover .MuiCardActions-root': {
      opacity: 1
    },

    borderRadius: '8px',
    display: 'flex',
    flexDirection: 'column',

    h3: {
      color: theme.palette.text.primary,
      font: 'normal normal 600 20px/28px Roboto',
      letterSpacing: '0',

      margin: '0'
    },

    height: '210px',
    minWidth: '280px',

    p: {
      color: theme.palette.text.secondary,
      font: 'normal normal 400 16px/20px Roboto',
      letterSpacing: '0',

      margin: '0'
    },

    width: '320px'
  }
}));

export { useStyles };
