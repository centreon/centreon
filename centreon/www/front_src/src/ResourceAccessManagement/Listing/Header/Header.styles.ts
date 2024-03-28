import { makeStyles } from 'tss-react/mui';

const useHeaderStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(4)
  },
  icon: {
    color: theme.palette.text.secondary
  }
}));

export default useHeaderStyles;
