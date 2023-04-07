import { makeStyles } from 'tss-react/mui';

const useDashboardStyles = makeStyles()((theme) => ({
  container: {
    '& .react-grid-item': {
      borderRadius: theme.shape.borderRadius,
      transition: theme.transitions.create('all', {
        duration: theme.transitions.duration.short,
        easing: theme.transitions.easing.easeOut
      })
    },
    '& .react-grid-item > .react-resizable-handle': {
      backgroundColor: theme.palette.action.focus,
      backgroundImage: 'none',
      borderRadius: theme.shape.borderRadius,
      opacity: 0
    },
    '& .react-grid-item > .react-resizable-handle.react-resizable-handle-e': {
      height: `calc(100% - ${theme.spacing(3)})`,
      marginTop: 0,
      top: 0,
      transform: 'rotate(0deg)',
      width: theme.spacing(1)
    },
    '& .react-grid-item > .react-resizable-handle.react-resizable-handle-s': {
      height: theme.spacing(1),
      left: 0,
      marginLeft: 0,
      transform: 'rotate(0deg)',
      width: `calc(100% - ${theme.spacing(3)})`
    },
    '& .react-grid-item > .react-resizable-handle.react-resizable-handle-se': {
      height: theme.spacing(2),
      transform: 'rotate(0deg)',
      width: theme.spacing(2)
    },
    '& .react-grid-item > .react-resizable-handle::after': {
      content: 'none'
    },
    '& .react-grid-item.react-draggable-dragging': {
      boxShadow: theme.shadows[3]
    },
    '& .react-grid-item.react-grid-placeholder': {
      display: 'none'
    },
    '& .react-grid-item.resizing': {
      boxShadow: theme.shadows[3]
    },
    '& .react-resizable-handle:hover': {
      opacity: 1
    }
  }
}));

export default useDashboardStyles;
