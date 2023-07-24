import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  contactAccessRightsInput: {
    alignItems: 'center',
    display: 'flex',
    flexGrow: 1,
    gap: theme.spacing(2),
    maxWidth: '520px',

    width: '100%'
  },
  contactInput: {
    '& .MuiOutlinedInput-root, & .MuiInputBase-input.MuiOutlinedInput-input': {
      height: 'unset',
      lineHeight: '1.5'
    },
    flexGrow: 1
  }
}));

export { useStyles };
