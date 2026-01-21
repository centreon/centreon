import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  titleGroup: {
    fontSize: theme.typography.subtitle1.fontSize,
    fontWeight: theme.typography.fontWeightMedium
  }
}));

export const useIconStyles = makeStyles()((theme) => ({
  icon: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1)
  }
}));
