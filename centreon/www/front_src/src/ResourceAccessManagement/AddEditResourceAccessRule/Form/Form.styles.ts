import { makeStyles } from 'tss-react/mui';

const useFormStyles = makeStyles()((theme) => ({
  form: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: '1fr 1fr'
  },
  rightBox: {
    display: 'flex',
    flexDirection: 'column'
  }
}));

export default useFormStyles;
