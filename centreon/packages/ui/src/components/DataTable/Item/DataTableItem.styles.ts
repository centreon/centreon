import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between'
  },
  cardActions: {
    flexShrink: 0,
    padding: 0
  },
  cardContent: {
    alignItems: 'flex-start',
    display: 'flex',
    gap: theme.spacing(1),
    justifyContent: 'space-between',
    padding: theme.spacing(2),
    zIndex: 1
  },
  cardContentText: {
    minWidth: 0
  },
  dataTableItem: {
    '&:hover img[alt*="thumbnail"]': {
      transform: 'scale(1.1)',
      transformOrigin: 'center'
    },
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    flexDirection: 'column',
    height: '200px',
    p: {
      color: theme.palette.text.secondary,
      letterSpacing: '0',
      margin: '0'
    },
    position: 'relative'
  },
  description: {
    marginLeft: theme.spacing(1),
    maxHeight: '42px',
    overflow: 'hidden'
  },
  thumbnail: {
    height: '100%',
    objectFit: 'cover',
    objectPosition: 'top',
    transition: 'transform 150ms ease-out',
    width: '100%'
  },
  thumbnailArea: {
    display: 'block',
    flex: 1,
    minHeight: 0,
    overflow: 'hidden',
    position: 'relative'
  },
  title: {
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  }
}));

export { useStyles };
