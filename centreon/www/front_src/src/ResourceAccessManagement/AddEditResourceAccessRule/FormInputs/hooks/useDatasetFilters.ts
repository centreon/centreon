import { useMemo } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { equals } from 'ramda';

import { Dataset } from '../../../models';

type UseDatasetFiltersState = {
  addDatasetFilter: () => void;
  datasetFilters: Array<Array<Dataset>>;
  deleteDatasetFilter: (index: number) => () => void;
};

const useDatasetFilters = (): UseDatasetFiltersState => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const datasetFilters = useMemo<Array<Array<Dataset>> | undefined>(
    () => values.datasetFilters,
    [values.datasetFilters]
  );

  const addDatasetFilter = (): void => {
    setFieldValue('datasetFilters', [
      ...(datasetFilters || []),
      [
        {
          resourceType: '',
          resources: []
        }
      ]
    ]);
  };

  const deleteDatasetFilter = (index: number) => (): void => {
    setFieldValue(
      'datasetFilters',
      (datasetFilters || []).filter((_, i) => !equals(i, index))
    );
  };

  return {
    addDatasetFilter,
    datasetFilters: datasetFilters || [],
    deleteDatasetFilter
  };
};

export default useDatasetFilters;
