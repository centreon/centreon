import { makeStyles } from 'tss-react/mui';

export const useTooltipStyles = makeStyles()((theme) => ({
  tooltip: {
    '[data-disablePadding="true"]': {
      padding: theme.spacing(0)
    },
    backgroundColor: theme.palette.background.paper,
    borderRadius: theme.shape.borderRadius,
    boxShadow: theme.shadows[3],
    color: theme.palette.text.primary,
    fontSize: theme.typography.caption.fontSize,
    fontWeight: theme.typography.caption.fontWeight,
    maxWidth: 'none',
    padding: theme.spacing(0.5, 1)
  }
}));
