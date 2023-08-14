import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles<{
  fullscreenMarginLeft?: string;
  fullscreenMarginTop?: string;
}>()((theme, props) => ({
  modal: {
    '& .MuiDialog-paper': {
      gap: theme.spacing(2),
      padding: theme.spacing(2)
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
      borderRadius: 0,
      height: `calc(100vh - ${props?.fullscreenMarginTop || '0px'})`,
      margin: 0,
      maxWidth: 'unset',
      width: `calc(100vw - ${props?.fullscreenMarginLeft || '0px'})`
    },
    '&[data-size="large"] .MuiDialog-paper': {
      maxWidth: '640px',
      width: '640px'
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
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2),
    justifyContent: 'flex-end'
  },
  modalBody: {
    '& > p': {
      '&:first-of-type': {
        margin: theme.spacing(0, 0, 1, 0)
      },
      color: theme.palette.text.secondary,
      margin: theme.spacing(1, 0, 1, 0),
      width: '90%'
    },
    height: '100%'
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
