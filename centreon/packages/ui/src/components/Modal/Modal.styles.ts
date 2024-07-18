import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles<{
  bottom?: number;
  left?: number;
  right?: number;
  top?: number;
}>()((theme, props) => ({
  modal: {
    '& .MuiDialog-paper': {
      gap: theme.spacing(2),
      padding: theme.spacing(2.5)
    },
    '&[data-size="fullscreen"]': {
      zIndex: 0
    },
    '&[data-size="fullscreen"] .MuiBackdrop-root': {
      display: 'none'
    },
    '&[data-size="fullscreen"] .MuiDialog-container': {
      alignItems: 'flex-end',
      height: '100vh',
      justifyContent: 'flex-end'
    },
    '&[data-size="fullscreen"] .MuiDialog-paper': {
      bottom: props?.bottom ?? 0,
      height: 'calc(100vh - 90px)',
      left: props?.left ?? 0,
      margin: 0,
      maxWidth: 'unset',
      paddingBottom: theme.spacing(8),
      position: 'absolute',
      right: props?.right ?? 0,
      top: props?.top ?? 0,
      transition: theme.transitions.create('left')
    },
    '&[data-size="large"] .MuiDialog-paper': {
      maxWidth: '604px',
      width: '604px'
    },
    '&[data-size="medium"] .MuiDialog-paper': {
      width: '520px'
    },
    '&[data-size="small"] .MuiDialog-paper': {
      width: '400px'
    },
    '&[data-size="xlarge"] .MuiDialog-paper': {
      maxWidth: '1400px',
      width: 'calc(100% - 64px)'
    }
  },
  modalActions: {
    '&[data-fixed="true"]': {
      background: theme.palette.background.paper,
      padding: theme.spacing(1, 2.5, 2.5, 0),
      position: 'fixed',
      width: '100%'
    },
    bottom: 0,
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2),
    justifyContent: 'flex-end',
    padding: theme.spacing(1, 0, 0, 0),
    right: 0,
    zIndex: theme.zIndex.modal
  },
  modalBody: {
    '& > p': {
      '&:first-of-type': {
        margin: theme.spacing(0, 0, 1, 0)
      },
      margin: theme.spacing(1, 0, 1, 0),
      width: '90%'
    }
  },
  modalCloseButton: {
    position: 'absolute',
    right: theme.spacing(1),
    svg: {
      opacity: 0.6
    },
    top: theme.spacing(1)
  },
  modalHeader: {
    '& .MuiDialogTitle-root': {
      padding: theme.spacing(0)
    },
    display: 'flex',
    gap: theme.spacing(2),

    justifyContent: 'space-between'
  }
}));

export { useStyles };
