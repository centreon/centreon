import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between'
  },
  cardActions: {
    backgroundColor: theme.palette.background.paper,
    bottom: 0,
    position: 'absolute',
    width: '100%'
  },
  cardContent: {
    padding: theme.spacing(2),
    zIndex: 1
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
    height: '250px',
    justifyContent: 'space-between',
    p: {
      color: theme.palette.text.secondary,
      letterSpacing: '0',
      margin: '0'
    },
    position: 'relative'
  },
  description: {
    maxHeight: '42px',
    overflow: 'hidden'
  },
  thumbnail: {
    height: theme.spacing(10),
    objectFit: 'cover',
    objectPosition: 'top',
    width: '100%'
  }
}));

export { useStyles };
