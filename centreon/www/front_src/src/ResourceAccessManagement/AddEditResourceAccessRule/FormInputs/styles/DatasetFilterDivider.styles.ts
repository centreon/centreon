import { makeStyles } from 'tss-react/mui';

export const useDatasetFilterDividerStyles = makeStyles()((theme) => ({
  addIcon: {
    color: theme.palette.common.white,
    margin: '0px'
  },
  divider: {
    marginBottom: theme.spacing(2),
    width: '90%'
  }
}));
