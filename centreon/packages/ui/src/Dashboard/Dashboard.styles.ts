import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/material';

export const useDashboardLayoutStyles = makeStyles<boolean>()(
  (theme, isStatic: boolean) => ({
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
        backgroundColor: alpha(theme.palette.primary.main, 0.2)
      },
      '& .react-grid-item.resizing': {
        boxShadow: theme.shadows[3]
      },
      '& .react-resizable-handle': {
        backgroundColor: theme.palette.action.focus,
        backgroundImage: 'none',
        borderRadius: theme.shape.borderRadius,
        display: isStatic ? 'none' : 'block',
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
      },
      position: 'relative'
    }
  })
);

export const useDashboardItemStyles = makeStyles<{ hasHeader: boolean }>()(
  (theme, { hasHeader }) => ({
    widgetContainer: {
      '&[data-padding="false"]': {
        padding: 0
      },
      background: theme.palette.background.widget,
      border: 'none',
      borderRadius: theme.spacing(1),
      height: '100%',
      width: '100%'
    },
    widgetContent: {
      height: hasHeader
        ? `calc(100% - ${theme.spacing(3.5)} - ${theme.spacing(0.5)})`
        : `calc(100% - ${theme.spacing(3.5)})`
    },
    widgetHeader: {
      '&:hover': {
        backgroundColor: theme.palette.action.hover
      },
      '&[data-canMove="false"]': {
        cursor: 'default'
      },
      '&[data-canMove="true"]': {
        cursor: 'move'
      },
      padding: theme.spacing(0, 1.5),
      position: 'relative'
    },
    widgetHeaderDraggable: {
      height: '100%',
      position: 'absolute',
      width: '95%'
    },
    widgetPadding: {
      overflowX: 'auto',
      padding: theme.spacing(0.5, 1.5, 1.5),
      position: 'relative'
    }
  })
);
