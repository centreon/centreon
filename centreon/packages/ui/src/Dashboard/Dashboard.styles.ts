import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/material';

export const useDashboardLayoutStyles = makeStyles()((theme) => ({
  container: {
    '& .react-grid-item': {
      borderRadius: theme.shape.borderRadius,
      transition: theme.transitions.create('all', {
        delay: 0,
        duration: 100,
        easing: theme.transitions.easing.easeOut
      })
    },
    '& .react-grid-item.react-draggable-dragging': {
      boxShadow: theme.shadows[3]
    },
    '& .react-grid-item.react-grid-placeholder': {
      backgroundColor: alpha(theme.palette.primary.main, 0.7)
    },
    '& .react-grid-item.resizing': {
      boxShadow: theme.shadows[3]
    },
    '& .react-resizable-handle': {
      backgroundColor: theme.palette.action.focus,
      backgroundImage: 'none',
      borderRadius: theme.shape.borderRadius,
      display: 'block',
      opacity: 0,
      position: 'absolute'
    },
    '& .react-resizable-handle.react-resizable-handle-e': {
      cursor: 'ew-resize',
      height: `calc(100% - ${theme.spacing(3)})`,
      marginTop: 0,
      right: 0,
      top: 0,
      transform: 'rotate(0deg)',
      width: theme.spacing(1)
    },
    '& .react-resizable-handle.react-resizable-handle-s': {
      bottom: 0,
      cursor: 'ns-resize',
      height: theme.spacing(1),
      left: 0,
      marginLeft: 0,
      transform: 'rotate(0deg)',
      width: `calc(100% - ${theme.spacing(3)})`
    },
    '& .react-resizable-handle.react-resizable-handle-se': {
      bottom: 0,
      cursor: 'nwse-resize',
      height: theme.spacing(2),
      right: 0,
      transform: 'rotate(0deg)',
      width: theme.spacing(2)
    },
    '& .react-resizable-handle::after': {
      content: 'none'
    },
    '& .react-resizable-handle:hover': {
      opacity: 1
    }
  }
}));

export const useDashboardItemStyles = makeStyles()((theme) => ({
  widgetContainer: {
    height: '100%',
    width: '100%'
  },
  widgetContent: {
    height: '100%',
    padding: theme.spacing(1, 2)
  },
  widgetHeader: {
    '&:hover': {
      backgroundColor: theme.palette.action.hover
    },
    cursor: 'move',
    padding: theme.spacing(0.5, 2)
  }
}));
