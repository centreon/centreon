import { makeStyles } from 'tss-react/mui';

export const useSliderStyles = makeStyles()((theme) => ({
  field: {
    width: 'auto'
  },
  input: {
    width: theme.spacing(9)
  },
  inputContainer: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  slider: {
    maxWidth: '300px'
  },
  sliderContainer: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(3)
  }
}));
