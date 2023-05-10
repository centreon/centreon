import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  listItem: {
    display: 'flex',
    flexDirection: 'column',

    borderRadius: '8px',

    minWidth: '280px',
    width: '320px',
    height: '210px',


    '& .MuiCardActionArea-root': {
      height: '100%',
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'space-between',
      alignItems: 'flex-start',

    },

    '& .MuiCardActions-root': {
      display: 'flex',
      flexDirection: 'row-reverse',
      gap: theme.spacing(1),

      opacity: 0,
    },
    '&:hover .MuiCardActions-root': {
      opacity: 1,
    },


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
