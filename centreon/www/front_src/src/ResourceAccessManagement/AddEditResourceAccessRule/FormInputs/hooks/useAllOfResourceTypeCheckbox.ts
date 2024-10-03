import { ChangeEvent } from 'react';

import { useFormikContext } from 'formik';
import { useAtom } from 'jotai';
import { equals } from 'ramda';

import { selectedDatasetFiltersAtom } from '../../../atom';
import { Dataset, ResourceAccessRule, ResourceTypeEnum } from '../../../models';
import {
  labelAllBusinessViews,
  labelAllHostGroups,
  labelAllHosts,
  labelAllServiceGroups
} from '../../../translatedLabels';

interface UseAllOfResourceTypeCheckboxState {
  checkboxLabel: string;
  checked: boolean;
  onChange: (event: ChangeEvent<HTMLInputElement>) => void;
}

const allOfResourceTypeLabels = {
  [ResourceTypeEnum.HostGroup]: labelAllHostGroups,
  [ResourceTypeEnum.Host]: labelAllHosts,
  [ResourceTypeEnum.ServiceGroup]: labelAllServiceGroups,
  [ResourceTypeEnum.BusinessView]: labelAllBusinessViews
};

export const useAllOfResourceTypeCheckbox = (
  datasetFilter: Array<Dataset>,
  datasetFilterIndex: number,
  datasetIndex: number,
  resourceType: ResourceTypeEnum
): UseAllOfResourceTypeCheckboxState => {
  const [selectedDatasetFilters, setSelectedDatasetFilters] = useAtom(
    selectedDatasetFiltersAtom
  );

  const { setFieldValue, setFieldTouched } =
    useFormikContext<ResourceAccessRule>();

  const checkboxLabel = allOfResourceTypeLabels[resourceType];

  const onChange = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(
      `datasetFilters.${datasetFilterIndex}.${datasetIndex}.resources`,
      []
    );
    setFieldValue(
      `datasetFilters.${datasetFilterIndex}.${datasetIndex}.allOfResourceType`,
      event.target.checked
    );
    setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
    setSelectedDatasetFilters(
      selectedDatasetFilters.map((datasets, indexFilter) => {
        if (equals(indexFilter, datasetFilterIndex)) {
          return selectedDatasetFilters[indexFilter].map((dataset, i) => {
            if (equals(i, datasetIndex)) {
              return {
                allOfResourceType: event.target.checked,
                ids: [],
                type: dataset.type
              };
            }

            return dataset;
          });
        }

        return datasets;
      })
    );
  };

  return {
    checkboxLabel,
    checked: datasetFilter[datasetIndex].allOfResourceType,
    onChange
  };
};
