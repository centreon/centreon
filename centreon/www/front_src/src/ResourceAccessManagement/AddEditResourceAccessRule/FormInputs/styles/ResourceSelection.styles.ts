import { makeStyles } from 'tss-react/mui';

export const useResourceSelectionStyles = makeStyles()((theme) => ({
  resourceSelectionContainter: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  },
  resourceSelectionTitle: {
    color: theme.palette.primary.main,
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightMedium,
    paddingBottom: theme.spacing(0.5)
  }
}));
