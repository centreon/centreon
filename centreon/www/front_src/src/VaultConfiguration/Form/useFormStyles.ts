import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  buttons: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1.5),
    justifyContent: 'space-between'
  },
  group: {
    width: '300px'
  },
  loading: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  skeleton: {
    height: '37px'
  }
}));
