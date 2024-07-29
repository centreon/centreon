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
  parametersTitle: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1.5),
    margin: theme.spacing(1.5, 0, 0.5)
  },
  parametersTitleText: {
    fontSize: theme.typography.subtitle1.fontSize,
    fontWeight: theme.typography.fontWeightMedium
  },
  parametersTitleTooltip: {
    color: theme.palette.primary.main,
    fontSize: theme.spacing(2.5)
  }
}));
