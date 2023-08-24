import { makeStyles } from 'tss-react/mui';

export const useCollapsibleItemStyles = makeStyles()((theme) => ({
  accordion: {
    border: 'none',
    borderBottom: `1px solid ${theme.palette.divider}`
  },
  accordionDetails: {
    padding: theme.spacing(0, 2, 2)
  },
  accordionSummary: {
    margin: theme.spacing(1.5, 0)
  }
}));
