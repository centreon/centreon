import { makeStyles } from 'tss-react/mui';

export const useBlockButtonsStyles = makeStyles()((theme) => ({
  autocomplete: {
    width: theme.spacing(17)
  }
}));

export const useAlignPickerStyles = makeStyles()((theme) => ({
  button: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  option: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  }
}));
