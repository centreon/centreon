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
        background: `${alpha(theme.palette.primary.main, 0.4)} !important`
      },
      '& .react-grid-item.resizing': {
        boxShadow: theme.shadows[3]
      },
      '& .react-resizable-handle': {
        display: isStatic ? 'none' : 'flex',
        position: 'absolute',
        opacity: 0,
        transition: theme.transitions.create('opacity')
      },
      '& .react-resizable-handle-e': {
        '& .handle-content-e': {
          backgroundColor: theme.palette.action.focus,
          backgroundImage: 'none',
          borderRadius: theme.shape.borderRadius,
          display: isStatic ? 'none' : 'block',
          height: 'calc(100% / 3)',
          width: '100%'
        },
        cursor: 'ew-resize',
        height: `calc(100% - ${theme.spacing(3)})`,
        marginTop: 0,
        right: 0,
        top: 0,
        width: theme.spacing(0.75),
        alignItems: 'center'
      },
      '& .react-resizable-handle-w': {
        '& .handle-content-w': {
          backgroundColor: theme.palette.action.focus,
          backgroundImage: 'none',
          borderRadius: theme.shape.borderRadius,
          display: isStatic ? 'none' : 'block',
          height: 'calc(100% / 3)',
          width: '100%'
        },
        cursor: 'ew-resize',
        height: `calc(100% - ${theme.spacing(3)})`,
        marginTop: 0,
        left: 0,
        top: 0,
        width: theme.spacing(0.75),
        alignItems: 'center'
      },

      '& .react-resizable-handle-s': {
        justifyContent: 'center',
        '& .handle-content-s': {
          backgroundColor: theme.palette.action.focus,
          backgroundImage: 'none',
          borderRadius: theme.shape.borderRadius,
          display: isStatic ? 'none' : 'block',
          width: 'calc(100% / 4)'
        },
        bottom: 4,
        cursor: 'ns-resize',
        height: theme.spacing(0.75),
        left: 0,
        marginLeft: 0,
        width: `calc(100% - ${theme.spacing(1)})`
      },
      '& .react-resizable-handle-se': {
        '& .handle-content-se': {
          backgroundColor: theme.palette.action.focus,
          backgroundImage: 'none',
          borderRadius: theme.shape.borderRadius,
          display: isStatic ? 'none' : 'block',
          width: '100%',
          height: '100%'
        },
        bottom: 4,
        cursor: 'nwse-resize',
        height: theme.spacing(1.5),
        right: 0,
        width: theme.spacing(1.5),
        opacity: 0.7
      },
      '& .react-resizable-handle-sw': {
        '& .handle-content-sw': {
          backgroundColor: theme.palette.action.focus,
          backgroundImage: 'none',
          borderRadius: theme.shape.borderRadius,
          display: isStatic ? 'none' : 'block',
          width: '100%',
          height: '100%'
        },
        bottom: 4,
        cursor: 'nesw-resize',
        height: theme.spacing(1.5),
        left: 0,
        width: theme.spacing(1.5),
        opacity: 0.7
      },
      '& .react-resizable-handle:hover': {
        opacity: 1
      },
      '& .react-resizable-handle::after': {
        content: 'none'
      },
      position: 'relative',
      height: '100%'
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
    widgetSubContainer: {
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
      '&[data-can-move="false"]': {
        cursor: 'default'
      },
      '&[data-can-move="true"]': {
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
