import { makeStyles } from 'tss-react/mui';

export const useDatasetFiltersStyles = makeStyles()((theme) => ({
  datasetFiltersComposition: {
    display: 'flex'
  },
  datasetFiltersContainer: {
    display: 'flex',
    flexDirection: 'column'
  },
  datasetFiltersDivider: {
    borderStyle: 'dashed',
    marginBottom: theme.spacing(3),
    width: '90%'
  }
}));
