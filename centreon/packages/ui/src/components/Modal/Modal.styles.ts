import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  modal: {
    '& .MuiDialog-paper': {
      gap: theme.spacing(2),
      padding: theme.spacing(2)
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
      '&:first-child': {
        margin: theme.spacing(0, 0, 1, 0)
      },
      color: theme.palette.text.secondary,
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
