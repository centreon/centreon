/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { equals, flatten, isEmpty } from 'ramda';

import useDatasetFilters from '../hooks/useDatasetFilters';
import { Dataset } from '../../../models';
import { useDatasetFiltersStyles } from '../styles/DatasetFilters.styles';

import DatasetFilter from './DatasetFilter';
import AddDatasetButton from './AddDatasetButton';
import DeleteDatasetButton from './DeleteDatasetButton';
import DatasetFilterDivider from './DatasetFilterDivider';

const DatasetFilters = (): ReactElement => {
  const { classes } = useDatasetFiltersStyles();
  const { addDatasetFilter, datasetFilters, deleteDatasetFilter } =
    useDatasetFilters();

  const areResourcesFilled = (datasets: Array<Dataset>): boolean =>
    datasets?.every(
      ({ resourceType, resources }) =>
        !isEmpty(resourceType) && !isEmpty(resources)
    );

  return (
    <div>
      {datasetFilters.map((datasetFilter, index) => (
        <div
          className={classes.datasetFiltersContainer}
          key={`${index}-datasetFilter`}
        >
          <div className={classes.datasetFiltersComposition}>
            <DatasetFilter
              areResourcesFilled={areResourcesFilled}
              datasetFilter={datasetFilter}
              datasetFilterIndex={index}
            />
            {datasetFilters.length > 1 && (
              <DeleteDatasetButton onDeleteItem={deleteDatasetFilter(index)} />
            )}
          </div>
          {!equals(datasetFilters.length - 1, index) && (
            <DatasetFilterDivider />
          )}
        </div>
      ))}
      <AddDatasetButton
        addButtonDisabled={
          !isEmpty(
            flatten(datasetFilters).filter(
              (dataset) =>
                isEmpty(dataset.resourceType) || isEmpty(dataset.resources)
            )
          )
        }
        onAddItem={addDatasetFilter}
      />
    </div>
  );
};

export default DatasetFilters;
