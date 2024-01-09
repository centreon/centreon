/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { isEmpty } from 'ramda';

import useDatasetFilters from '../hooks/useDatasetFilters';
import { Dataset } from '../../../models';
import { useDatasetFiltersStyles } from '../styles/DatasetFilters.styles';

import DatasetFilter from './DatasetFilter';
import AddDatasetButton from './AddDatasetButton';
import DeleteDatasetButton from './DeleteDatasetButton';

const DatasetFilters = (): ReactElement => {
  const { classes } = useDatasetFiltersStyles();
  const { addDatasetFilter, datasetFilters, deleteDatasetFilter } =
    useDatasetFilters();

  const areResourcesFilled = (datasets: Array<Dataset>): boolean =>
    datasets?.every(
      ({ resourceType, resources }) =>
        !isEmpty(resourceType) && !isEmpty(resources)
    );

  const addDatasetFilterButtonDisabled = (
    datasetFiltersArray: Array<Array<Dataset>>
  ): boolean =>
    datasetFiltersArray.length <= 1 &&
    !areResourcesFilled(datasetFiltersArray[0]);

  return (
    <div>
      {datasetFilters.map((datasetFilter, index) => (
        <div
          className={classes.datasetFiltersContainer}
          key={`${index}-datasetFilter`}
        >
          <DatasetFilter
            areResourcesFilled={areResourcesFilled}
            datasetFilter={datasetFilter}
            datasetFilterIndex={index}
          />
          <DeleteDatasetButton
            deleteButtonHidden={datasetFilters.length < 2}
            onDeleteItem={deleteDatasetFilter(index)}
          />
        </div>
      ))}
      <AddDatasetButton
        addButtonDisabled={addDatasetFilterButtonDisabled(datasetFilters)}
        onAddItem={addDatasetFilter}
      />
    </div>
  );
};

export default DatasetFilters;
