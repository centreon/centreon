import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
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
  },
  buttons: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1.5),
    alignSelf: 'flex-end'
  }
}));
