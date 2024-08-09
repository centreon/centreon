import { makeStyles } from 'tss-react/mui';

export const useYAxisStyles = makeStyles()((theme) => ({
  axisInput: {
    maxHeight: theme.spacing(3.5),
    minHeight: theme.spacing(3.5)
  },
  unitContainer: {
    marginTop: '2px'
  }
}));
