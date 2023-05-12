import { makeStyles } from 'tss-react/mui';

const useDialogStyles = makeStyles()((theme) => ({
  dialog: {
    '& .MuiDialog-paper': {
      minWidth: '400px',
      padding: theme.spacing(2)
    }
  }
}));

const useDialogTitleStyles = makeStyles()((theme) => ({
  dialogTitle: {
    padding: theme.spacing(1, 2)
  }
}));
export { useDialogStyles, useDialogTitleStyles };
