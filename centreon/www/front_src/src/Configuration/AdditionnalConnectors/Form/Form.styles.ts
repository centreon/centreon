import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  titleGroup: {
    fontWeight: theme.typography.fontWeightMedium,
    fontSize: theme.typography.subtitle1.fontSize
  },
  parametersTitleText: {
    fontSize: theme.typography.subtitle1.fontSize,
    fontWeight: theme.typography.fontWeightMedium,
    marginTop: theme.spacing(1)
  }
}));
