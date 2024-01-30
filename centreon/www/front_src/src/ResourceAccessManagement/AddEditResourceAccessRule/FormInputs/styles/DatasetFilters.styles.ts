import { makeStyles } from 'tss-react/mui';

export const useDatasetFiltersStyles = makeStyles()(() => ({
  datasetFiltersComposition: {
    display: 'flex'
  },
  datasetFiltersContainer: {
    display: 'flex',
    flexDirection: 'column'
  }
}));
