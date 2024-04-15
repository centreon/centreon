/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { FormHelperText } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';

import { MultiConnectedAutocompleteField, SelectField } from '@centreon/ui';
import { ItemComposition } from '@centreon/ui/components';

import { Dataset, ResourceTypeEnum } from '../../../models';
import {
  labelDelete,
  labelAddFilter,
  labelSelectResource,
  labelSelectResourceType,
  labelAllResourcesSelected,
  labelAllHostsSelected,
  labelAllHostGroupsSelected,
  labelAllServiceGroupsSelected
} from '../../../translatedLabels';
import useDatasetFilter from '../hooks/useDatasetFilter';
import { useDatasetFilterStyles } from '../styles/DatasetFilter.styles';

import AllOfResourceTypeCheckbox from './AllOfResourceTypeCheckbox';

type Props = {
  areResourcesFilled: (datasets: Array<Dataset>) => boolean;
  datasetFilter: Array<Dataset>;
  datasetFilterIndex: number;
};

const DatasetFilter = ({
  areResourcesFilled,
  datasetFilter,
  datasetFilterIndex
}: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useDatasetFilterStyles();

  const {
    addResource,
    changeResourceType,
    changeResources,
    deleteResource,
    deleteResourceItem,
    error,
    getResourceBaseEndpoint,
    getResourceTypeOptions,
    getSearchField,
    lowestResourceTypeReached
  } = useDatasetFilter(datasetFilter, datasetFilterIndex);

  const deleteButtonHidden = datasetFilter.length <= 1;

  const displayAllOfResourceTypeCheckbox = (
    resourceType: ResourceTypeEnum
  ): boolean =>
    equals(resourceType, ResourceTypeEnum.HostGroup) ||
    equals(resourceType, ResourceTypeEnum.Host) ||
    equals(resourceType, ResourceTypeEnum.ServiceGroup);

  const labelsForSelectedResources = {
    [ResourceTypeEnum.Host]: labelAllHostsSelected,
    [ResourceTypeEnum.HostGroup]: labelAllHostGroupsSelected,
    [ResourceTypeEnum.ServiceGroup]: labelAllServiceGroupsSelected
  };

  const getLabelForSelectedResources = (index: number): string => {
    if (datasetFilter[index]?.allOfResourceType) {
      return labelsForSelectedResources[datasetFilter[index].resourceType];
    }

    if (equals(datasetFilter[index].resourceType, ResourceTypeEnum.All)) {
      return labelAllResourcesSelected;
    }

    return labelSelectResource;
  };

  return (
    <div className={classes.resourceComposition}>
      <ItemComposition
        IconAdd={<AddIcon />}
        addbuttonDisabled={
          !areResourcesFilled(datasetFilter) || lowestResourceTypeReached()
        }
        labelAdd={t(labelAddFilter)}
        onAddItem={addResource}
      >
        {datasetFilter.map((resource, resourceIndex) => (
          <div
            className={classes.resourceCompositionItem}
            key={`${resourceIndex}${resource.resources[0]}`}
          >
            <ItemComposition.Item
              className={classes.resourceDataset}
              deleteButtonHidden={deleteButtonHidden}
              key={`${resourceIndex}${resource.resources[0]}`}
              labelDelete={t(labelDelete)}
              onDeleteItem={deleteResource(resourceIndex)}
            >
              <SelectField
                aria-label={`${labelSelectResourceType}`}
                className={classes.resourceType}
                label={t(labelSelectResourceType) as string}
                options={getResourceTypeOptions(resourceIndex)}
                selectedOptionId={resource.resourceType}
                onChange={changeResourceType(resourceIndex)}
              />
              <MultiConnectedAutocompleteField
                allowUniqOption
                chipProps={{
                  color: 'primary',
                  onDelete: (_, option): void =>
                    deleteResourceItem({
                      index: resourceIndex,
                      option,
                      resources: resource.resources
                    })
                }}
                className={classes.resources}
                dataTestId={labelSelectResource}
                disabled={
                  datasetFilter[resourceIndex].allOfResourceType ||
                  !resource.resourceType ||
                  equals(resource.resourceType, ResourceTypeEnum.All)
                }
                field={getSearchField(resource.resourceType)}
                getEndpoint={getResourceBaseEndpoint(
                  resourceIndex,
                  resource.resourceType
                )}
                label={t(getLabelForSelectedResources(resourceIndex))}
                limitTags={5}
                queryKey={`${resource.resourceType}-${resourceIndex}`}
                value={
                  datasetFilter[resourceIndex].allOfResourceType
                    ? []
                    : resource.resources || []
                }
                onChange={changeResources(resourceIndex)}
              />
            </ItemComposition.Item>
            {displayAllOfResourceTypeCheckbox(resource.resourceType) && (
              <AllOfResourceTypeCheckbox
                datasetFilterIndex={datasetFilterIndex}
                datasetIndex={resourceIndex}
                resourceType={resource.resourceType}
              />
            )}
          </div>
        ))}
      </ItemComposition>
      {error && <FormHelperText error>{t(error)}</FormHelperText>}
    </div>
  );
};

export default DatasetFilter;
