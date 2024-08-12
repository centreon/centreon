import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  additionalLabel: {
    fontSize: theme.typography.subtitle1.fontSize,
    fontWeight: theme.typography.fontWeightMedium,
    marginBottom: theme.spacing(1),
    marginTop: theme.spacing(0.5)
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%'
  },
  parametersTitleText: {
    fontSize: theme.typography.subtitle1.fontSize,
    fontWeight: theme.typography.fontWeightMedium,
    marginTop: theme.spacing(1)
  }
}));
