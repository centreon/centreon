import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  dataTableItem: {
    '& .MuiCardActionArea-root': {
      alignItems: 'flex-start',
      display: 'flex',
      flexDirection: 'column',
      height: '100%',
      justifyContent: 'space-between'
    },
    '& .MuiCardActions-root': {
      '& > span': {
        display: 'flex',
        gap: theme.spacing(1)
      },
      display: 'flex',

      justifyContent: 'space-between'
    },
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    flexDirection: 'column',
    height: '186px',
    p: {
      color: theme.palette.text.secondary,
      letterSpacing: '0',
      margin: '0'
    }
  }
}));

export { useStyles };
