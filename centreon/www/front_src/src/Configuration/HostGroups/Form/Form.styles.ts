import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  titleGroup: {
    fontWeight: theme.typography.fontWeightMedium,
    fontSize: theme.typography.subtitle1.fontSize
  }
}));

export const useIconStyles = makeStyles()((theme) => ({
  icon: {
    display: 'flex',
    gap: theme.spacing(0.75),
    alignItems: 'center'
  }
}));
