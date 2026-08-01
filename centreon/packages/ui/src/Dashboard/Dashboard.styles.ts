import { alpha } from '@mui/material';

import { makeStyles } from 'tss-react/mui';

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
        opacity: 0,
        position: 'absolute',
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
        alignItems: 'center',
        cursor: 'ew-resize',
        height: `calc(100% - ${theme.spacing(3)})`,
        marginTop: 0,
        right: 0,
        top: 0,
        width: theme.spacing(0.75)
      },

      '& .react-resizable-handle-s': {
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
        justifyContent: 'center',
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
          height: '100%',
          width: '100%'
        },
        bottom: 4,
        cursor: 'nwse-resize',
        height: theme.spacing(1.5),
        opacity: 0.7,
        right: 0,
        width: theme.spacing(1.5)
      },
      '& .react-resizable-handle-sw': {
        '& .handle-content-sw': {
          backgroundColor: theme.palette.action.focus,
          backgroundImage: 'none',
          borderRadius: theme.shape.borderRadius,
          display: isStatic ? 'none' : 'block',
          height: '100%',
          width: '100%'
        },
        bottom: 4,
        cursor: 'nesw-resize',
        height: theme.spacing(1.5),
        left: 0,
        opacity: 0.7,
        width: theme.spacing(1.5)
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
        alignItems: 'center',
        cursor: 'ew-resize',
        height: `calc(100% - ${theme.spacing(3)})`,
        left: 0,
        marginTop: 0,
        top: 0,
        width: theme.spacing(0.75)
      },
      '& .react-resizable-handle::after': {
        content: 'none'
      },
      '& .react-resizable-handle:hover': {
        opacity: 1
      },
      height: '100%',
      position: 'relative'
    }
  })
);

export const useDashboardItemStyles = makeStyles<{ hasHeader: boolean }>()(
  (theme, { hasHeader }) => ({
    widgetContainer: {
      '&:hover .cf-panel-resource-link': {
        opacity: 1,
        pointerEvents: 'auto'
      },
      '&[data-padding="false"]': {
        padding: 0
      },
      background: theme.palette.background.widget,
      border: `1px solid ${theme.palette.divider}`,
      borderRadius: theme.spacing(1),
      height: '100%',
      position: 'relative',
      width: '100%'
    },
    widgetContent: {
      height: hasHeader
        ? `calc(100% - ${theme.spacing(3.5)} - ${theme.spacing(0.5)})`
        : '100%'
    },
    widgetHeader: {
      padding: theme.spacing(0, 1.5),
      position: 'relative'
    },
    widgetHeaderCollapsed: {
      height: 0,
      overflow: 'hidden',
      padding: 0
    },
    widgetOverlayActions: {
      alignItems: 'center',
      backgroundColor: theme.palette.background.paper,
      borderRadius: theme.shape.borderRadius,
      display: 'flex',
      gap: theme.spacing(0.5),
      opacity: 0,
      position: 'relative',
      transition: theme.transitions.create(['box-shadow', 'opacity'])
    },
    widgetOverlayCorner: {
      '&:hover .cf-widget-drag-handle': {
        opacity: 1,
        pointerEvents: 'auto'
      },
      '&:hover .cf-widget-overlay-actions': {
        boxShadow: `0 0 0 4px ${alpha(theme.palette.primary.main, 0.15)}`,
        opacity: 1
      },
      '&:hover .cf-widget-overlay-info': {
        opacity: 0,
        pointerEvents: 'none'
      },
      position: 'absolute',
      right: theme.spacing(1),
      top: theme.spacing(1),
      zIndex: 1
    },
    widgetOverlayDragHandle: {
      alignItems: 'center',
      color: theme.palette.text.secondary,
      cursor: 'move',
      display: 'flex',
      justifyContent: 'center',
      opacity: 0,
      padding: theme.spacing(0.5),
      pointerEvents: 'none',
      transition: theme.transitions.create('opacity')
    },
    widgetOverlayInfo: {
      alignItems: 'center',
      display: 'flex',
      inset: 0,
      justifyContent: 'center',
      position: 'absolute',
      transition: theme.transitions.create('opacity')
    },
    widgetPadding: {
      overflowX: 'auto',
      padding: theme.spacing(0.5, 1.5, 1.5),
      position: 'relative'
    },
    widgetSubContainer: {
      height: '100%',
      width: '100%'
    }
  })
);
