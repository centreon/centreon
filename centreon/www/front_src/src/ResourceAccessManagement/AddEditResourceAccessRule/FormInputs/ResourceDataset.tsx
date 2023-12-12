/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { FormHelperText, Typography } from '@mui/material';
import FilterIcon from '@mui/icons-material/Tune';

import { ItemComposition } from '@centreon/ui/components';
import { MultiConnectedAutocompleteField, SelectField } from '@centreon/ui';

import {
  labelDelete,
  labelRefineFilter,
  labelResourceSelection,
  labelResourceType,
  labelSelectResource,
  labelSelectResourceType
} from '../../translatedLabels';

import { useResourceDatasetStyles } from './Inputs.styles';
import useResourceDataset from './useResourceDataset';

interface Props {
  propertyName: string;
}

const ResourceDataset = ({ propertyName }: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useResourceDatasetStyles();

  const {
    addResource,
    changeResources,
    changeResourceType,
    deleteResource,
    deleteResourceItem,
    error,
    getResourceBaseEndpoint,
    getSearchField,
    resourceTypeOptions,
    value
  } = useResourceDataset(propertyName);

  const deleteButtonHidden = value.length <= 1;

  return (
    <div className={classes.resourcesContainer}>
      <div className={classes.resourceComposition}>
        <ItemComposition
          IconAdd={<FilterIcon />}
          // addButtonHidden={}
          // addbuttonDisabled={}
          labelAdd={t(labelRefineFilter)}
          onAddItem={addResource}
        >
          {value.map((resource, index) => (
            <ItemComposition.Item
              className={classes.resourceCompositionItem}
              deleteButtonHidden={deleteButtonHidden}
              key={`${index}${resource.resources[0]}`}
              labelDelete={t(labelDelete)}
              onDeleteItem={deleteResource(index)}
            >
              <SelectField
                className={classes.resourceType}
                dataTestId={labelResourceType}
                label={t(labelSelectResourceType) as string}
                options={resourceTypeOptions}
                selectedOptionId={resource.resourceType}
                onChange={changeResourceType(index)}
              />
              <MultiConnectedAutocompleteField
                allowUniqOption
                chipProps={{
                  color: 'primary',
                  onDelete: (_, option): void =>
                    deleteResourceItem({
                      index,
                      option,
                      resources: resource.resources
                    })
                }}
                className={classes.resources}
                disabled={!resource.resourceType}
                field={getSearchField(resource.resourceType)}
                getEndpoint={getResourceBaseEndpoint(resource.resourceType)}
                label={t(labelSelectResource) as string}
                limitTags={2}
                queryKey={`${resource.resourceType}-${index}`}
                value={resource.resources || []}
                onChange={changeResources(index)}
              />
            </ItemComposition.Item>
          ))}
        </ItemComposition>
        {error && <FormHelperText error>{t(error)}</FormHelperText>}
      </div>
    </div>
  );
};

export default ResourceDataset;
