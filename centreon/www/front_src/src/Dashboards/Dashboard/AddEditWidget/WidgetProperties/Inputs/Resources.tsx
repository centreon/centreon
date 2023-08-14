/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';

import { Divider, FormHelperText, Typography } from '@mui/material';

import { Avatar, ItemComposition } from '@centreon/ui/components';
import { MultiConnectedAutocompleteField, SelectField } from '@centreon/ui';

import {
  labelAddResource,
  labelDelete,
  labelResourceType,
  labelResources,
  labelSelectAResource
} from '../../../translatedLabels';
import { useAddWidgetStyles } from '../../addWidget.styles';

import useResources from './useResources';
import { useResourceStyles } from './Inputs.styles';

interface Props {
  propertyName: string;
}

const Resources = ({ propertyName }: Props): JSX.Element => {
  const { classes } = useResourceStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();
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
      <div className={classes.resourcesHeader}>
        <Avatar compact className={avatarClasses.widgetAvatar}>
          2
        </Avatar>
        <Typography>{t(labelResources)}</Typography>
        <Divider className={classes.resourcesHeaderDivider} />
      </div>
      <ItemComposition labelAdd={t(labelAddResource)} onAddItem={addResource}>
        {value.map((resource, index) => (
          <ItemComposition.Item
            className={classes.resourceCompositionItem}
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
