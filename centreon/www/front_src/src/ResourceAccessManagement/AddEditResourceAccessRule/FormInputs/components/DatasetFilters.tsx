/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { isEmpty } from 'ramda';

import useDatasetFilters from '../hooks/useDatasetFilters';
import { Dataset } from '../../../models';

import DatasetFilter from './DatasetFilter';
import AddDatasetButton from './AddDatasetButton';

const DatasetFilters = (): ReactElement => {
  const { addDatasetFilter, datasetFilters, deleteDatasetFilter } =
    useDatasetFilters();

  const areResourcesFilled = (datasets: Array<Dataset>): boolean =>
    datasets?.every(
      ({ resourceType, resources }) =>
        !isEmpty(resourceType) && !isEmpty(resources)
    );

  const areDatasetFilterFilled = (
    datasetFiltersArray: Array<Array<Dataset>>
  ): boolean =>
    datasetFiltersArray.length <= 1 &&
    !areResourcesFilled(datasetFiltersArray[0]);

  return (
    <div>
      {datasetFilters.map((datasetFilter, index) => (
        <DatasetFilter
          areResourcesFilled={areResourcesFilled}
          datasetFilter={datasetFilter}
          datasetFilterIndex={index}
          key={`${index}-datasetFilter`}
        />
      ))}
      <AddDatasetButton
        addButtonDisabled={areDatasetFilterFilled(datasetFilters)}
        onAddItem={addDatasetFilter}
      />
    </div>
  );
};

export default DatasetFilters;
