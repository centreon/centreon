/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { equals, flatten, isEmpty, last } from 'ramda';

import { Divider } from '@mui/material';

import useDatasetFilters from '../hooks/useDatasetFilters';
import { Dataset } from '../../../models';
import { useDatasetFiltersStyles } from '../styles/DatasetFilters.styles';

import DatasetFilter from './DatasetFilter';
import DeleteDatasetButton from './DeleteDatasetButton';
import AddDatasetButton from './AddDatasetButton';

const DatasetFilters = (): ReactElement => {
  const { classes } = useDatasetFiltersStyles();
  const { addDatasetFilter, datasetFilters, deleteDatasetFilter } =
    useDatasetFilters();

  const areResourcesFilled = (datasets: Array<Dataset>): boolean =>
    (!isEmpty(last(datasets)?.resourceType) &&
      !isEmpty(last(datasets)?.resources)) ||
    (last(datasets)?.allOfResourceType as boolean);

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
            <Divider
              className={classes.datasetFiltersDivider}
              variant="middle"
            />
          )}
        </div>
      ))}
      <AddDatasetButton
        addButtonDisabled={
          !isEmpty(
            flatten(datasetFilters).filter(
              (dataset) =>
                !dataset.allOfResourceType &&
                (isEmpty(dataset.resourceType) || isEmpty(dataset.resources))
            )
          )
        }
        onAddItem={addDatasetFilter}
      />
    </div>
  );
};

export default DatasetFilters;
