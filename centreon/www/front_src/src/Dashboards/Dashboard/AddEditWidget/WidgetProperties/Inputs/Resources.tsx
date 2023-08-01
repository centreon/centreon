/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { FormHelperText, Typography } from '@mui/material';

import { ItemComposition } from '@centreon/ui/components';
import { MultiConnectedAutocompleteField, SelectField } from '@centreon/ui';

import {
  labelAdd,
  labelDelete,
  labelResourceType,
  labelResources,
  labelSelectAResource
} from '../../../translatedLabels';

import useResources from './useResources';
import { useResourceStyles } from './Inputs.styles';

interface Props {
  propertyName: string;
}

const Resources = ({ propertyName }: Props): JSX.Element => {
  const { classes } = useResourceStyles();
  const { t } = useTranslation();

  const {
    value,
    resourceTypeOptions,
    changeResourceType,
    addResource,
    deleteResource,
    changeResources,
    getResourceResourceBaseEndpoint,
    getSearchField,
    error
  } = useResources(propertyName);

  return (
    <div className={classes.resourcesContainer}>
      <Typography>{t(labelResources)}</Typography>
      <ItemComposition labelAdd={t(labelAdd)} onAddItem={addResource}>
        {value.map((resource, index) => (
          <ItemComposition.Item
            key={`${index}`}
            labelDelete={t(labelDelete)}
            onDeleteItem={deleteResource(index)}
          >
            <SelectField
              className={classes.resourceType}
              dataTestId={labelResourceType}
              label={t(labelResourceType) as string}
              options={resourceTypeOptions}
              selectedOptionId={resource.resourceType}
              onChange={changeResourceType(index)}
            />
            <MultiConnectedAutocompleteField
              className={classes.resources}
              disabled={!resource.resourceType}
              field={getSearchField(resource.resourceType)}
              getEndpoint={getResourceResourceBaseEndpoint(
                resource.resourceType
              )}
              label={t(labelSelectAResource)}
              labelKey={
                equals(resource.resourceType, 'service')
                  ? 'display_name'
                  : undefined
              }
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
  );
};

export default Resources;
