import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between'
  },
  checkbox: {
    '&.Mui-checked': {
      color: theme.palette.primary.main
    },
    color: theme.palette.primary.main
  },
  dataTableItem: {
    '& .MuiCardActionArea-root': {
      alignItems: 'flex-start',
      display: 'flex',
      flexDirection: 'column',
      height: '100%',
      justifyContent: 'flex-start'
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
    justifyContent: 'space-between',
    p: {
      color: theme.palette.text.secondary,
      letterSpacing: '0',
      margin: '0'
    }
  }
}));

export { useStyles };
