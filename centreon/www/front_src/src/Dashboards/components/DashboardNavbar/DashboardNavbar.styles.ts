import { makeStyles } from 'tss-react/mui';

export const useDashboardNavbarStyles = makeStyles()((theme) => ({
  link: {
    '&[data-selected="true"]': {
      color: theme.palette.primary.main,
      cursor: 'default',
      fontWeight: theme.typography.fontWeightBold
    },
    color: theme.palette.text.primary,
    cursor: 'pointer',
    fontWeight: theme.typography.fontWeightRegular
  },
  navbar: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(3)
  }
}));
