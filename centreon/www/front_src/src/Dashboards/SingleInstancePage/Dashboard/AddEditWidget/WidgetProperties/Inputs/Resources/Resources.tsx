/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { equals, isNil } from 'ramda';

import { Divider, FormHelperText, Typography } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';

import { Avatar, ItemComposition } from '@centreon/ui/components';
import {
  MultiConnectedAutocompleteField,
  SelectField,
  SingleConnectedAutocompleteField
} from '@centreon/ui';

import {
  labelAddFilter,
  labelDelete,
  labelResourceType,
  labelResources,
  labelSelectAResource,
  labelSelectResourceType
} from '../../../../translatedLabels';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { useResourceStyles } from '../Inputs.styles';
import { areResourcesFullfilled } from '../utils';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { WidgetPropertyProps } from '../../../models';

import useResources from './useResources';

const Resources = ({
  propertyName,
  singleResourceType,
  restrictedResourceTypes,
  required,
  useAdditionalResources
}: WidgetPropertyProps): JSX.Element => {
  const { classes } = useResourceStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();
  const { t } = useTranslation();

  const {
    value,
    getResourceTypeOptions,
    changeResourceType,
    addResource,
    deleteResource,
    changeResources,
    getResourceResourceBaseEndpoint,
    getSearchField,
    error,
    deleteResourceItem,
    getResourceStatic,
    changeResource,
    singleResourceSelection,
    isLastResourceInTree
  } = useResources({
    propertyName,
    required,
    restrictedResourceTypes,
    useAdditionalResources
  });

  const { canEditField } = useCanEditProperties();

  const deleteButtonHidden =
    !canEditField ||
    (value.length <= 1 && (required || isNil(required))) ||
    equals(value.length, 1);

  const isAddButtonHidden = !canEditField || singleResourceType;
  const isAddButtonDisabled =
    !areResourcesFullfilled(value) || isLastResourceInTree;

  return (
    <div className={classes.resourcesContainer}>
      <div className={classes.resourcesHeader}>
        <Avatar compact className={avatarClasses.widgetAvatar}>
          2
        </Avatar>
        <Typography className={classes.resourceTitle}>
          {t(labelResources)}
        </Typography>
        <Divider className={classes.resourcesHeaderDivider} />
      </div>
      <div className={classes.resourceComposition}>
        <ItemComposition
          displayItemsAsLinked
          IconAdd={<AddIcon />}
          addButtonHidden={isAddButtonHidden}
          addbuttonDisabled={isAddButtonDisabled}
          labelAdd={t(labelAddFilter)}
          onAddItem={addResource}
        >
          {value.map((resource, index) => (
            <ItemComposition.Item
              className={classes.resourceCompositionItem}
              deleteButtonHidden={
                deleteButtonHidden || getResourceStatic(resource.resourceType)
              }
              key={`${index}${resource.resources[0]}`}
              labelDelete={t(labelDelete)}
              onDeleteItem={deleteResource(index)}
            >
              <SelectField
                className={classes.resourceType}
                dataTestId={labelResourceType}
                disabled={
                  !canEditField || getResourceStatic(resource.resourceType)
                }
                label={t(labelSelectResourceType) as string}
                options={getResourceTypeOptions(index, resource)}
                selectedOptionId={resource.resourceType}
                onChange={changeResourceType(index)}
              />
              {singleResourceSelection ? (
                <SingleConnectedAutocompleteField
                  allowUniqOption
                  chipProps={{
                    color: 'primary'
                  }}
                  className={classes.resources}
                  disableClearable={false}
                  disabled={!canEditField || !resource.resourceType}
                  field={getSearchField(resource.resourceType)}
                  getEndpoint={getResourceResourceBaseEndpoint({
                    index,
                    resourceType: resource.resourceType
                  })}
                  label={t(labelSelectAResource)}
                  limitTags={2}
                  queryKey={`${resource.resourceType}-${index}`}
                  value={resource.resources[0] || undefined}
                  onChange={changeResource(index)}
                />
              ) : (
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
                  disabled={!canEditField || !resource.resourceType}
                  field={getSearchField(resource.resourceType)}
                  getEndpoint={getResourceResourceBaseEndpoint({
                    index,
                    resourceType: resource.resourceType
                  })}
                  label={t(labelSelectAResource)}
                  limitTags={2}
                  placeholder=""
                  queryKey={`${resource.resourceType}-${index}`}
                  value={resource.resources || []}
                  onChange={changeResources(index)}
                />
              )}
            </ItemComposition.Item>
          ))}
        </ItemComposition>
        {error && <FormHelperText error>{t(error)}</FormHelperText>}
      </div>
    </div>
  );
};

export default Resources;
