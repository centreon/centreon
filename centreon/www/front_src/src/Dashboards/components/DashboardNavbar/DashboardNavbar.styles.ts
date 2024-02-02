import { makeStyles } from 'tss-react/mui';

export const useDashboardNavbarStyles = makeStyles()((theme) => ({
  link: {
    '&[data-selected="true"]': {
      color: theme.palette.primary.main
    },
    color: theme.palette.text.primary,
    fontWeight: theme.typography.fontWeightBold
  },
  navbar: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(3)
  }
}));
