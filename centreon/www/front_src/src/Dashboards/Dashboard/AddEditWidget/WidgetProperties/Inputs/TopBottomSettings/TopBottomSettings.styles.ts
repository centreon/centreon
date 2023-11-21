import { makeStyles } from 'tss-react/mui';

export const useTopBottomSettingsStyles = makeStyles()((theme) => ({
  input: {
    width: theme.spacing(6)
  },
  toggleButtonGroup: {
    '& button': {
      border: 'none',
      borderRadius: '2px',
      color: theme.palette.primary.main
    },
    '& button[aria-pressed="true"]': {
      backgroundColor: theme.palette.primary.main,
      color: theme.palette.common.white
    },
    border: `1px solid ${theme.palette.primary.main}`
  },
  values: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  }
}));
