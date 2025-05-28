import { equals, isNil } from 'ramda';
/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';
import {
  CircularProgress,
  Divider,
  FormHelperText,
  Typography
} from '@mui/material';

import {
  MultiConnectedAutocompleteField,
  SelectField,
  SingleConnectedAutocompleteField
} from '@centreon/ui';
import { Avatar, ItemComposition } from '@centreon/ui/components';

import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import {
  labelAddFilter,
  labelDelete,
  labelResourceType,
  labelResources,
  labelSelectAResource,
  labelSelectResourceType
} from '../../../../translatedLabels';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import {
  WidgetDataResource,
  WidgetPropertyProps,
  WidgetResourceType
} from '../../../models';
import { useResourceStyles } from '../Inputs.styles';
import { areResourcesFullfilled } from '../utils';

import useResources from './useResources';

const Resources = ({
  propertyName,
  singleResourceType,
  restrictedResourceTypes,
  excludedResourceTypes,
  required,
  useAdditionalResources,
  forcedResourceType,
  defaultResourceTypes,
  selectType,
  overrideAddButtonVisibility,
  forceSingleAutocompleteConditions
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
    isLastResourceInTree,
    changeIdValue,
    hasSelectedHostForSingleMetricwidget,
    isValidatingResources,
    hideResourceDeleteButton,
    checkForceSingleAutocomplete
  } = useResources({
    excludedResourceTypes,
    propertyName,
    required,
    restrictedResourceTypes,
    useAdditionalResources,
    forcedResourceType,
    defaultResourceTypes
  });

  const { canEditField } = useCanEditProperties();

  const deleteButtonHidden =
    !canEditField ||
    (value.length <= 1 && (required || isNil(required))) ||
    equals(value.length, 1);

  const isAddButtonHidden = !canEditField || singleResourceType;
  const isAddButtonDisabled =
    !areResourcesFullfilled(value) || isLastResourceInTree;

  const getResourceTypeSelectedOptionId = (resourceType: WidgetResourceType) =>
    equals(resourceType, 'hostgroup')
      ? WidgetResourceType.hostGroup
      : resourceType;

  const getDefaultDisabledSelectType = (resourceType: WidgetResourceType) =>
    equals(selectType?.defaultResourceType, resourceType) &&
    selectType?.disabled;

  const getOverrideAddButtonVisibility = (value: Array<WidgetDataResource>) =>
    Boolean(
      value.find((resource) =>
        equals(
          overrideAddButtonVisibility?.matchedResourcesType,
          resource.resourceType
        )
      )
    );

  return (
    <div className={classes.resourcesContainer}>
      <div className={classes.resourcesHeader}>
        <Avatar compact className={avatarClasses.widgetAvatar}>
          2
        </Avatar>
        <Typography className={classes.resourceTitle}>
          {t(labelResources)}
        </Typography>
        {isValidatingResources && <CircularProgress size={16} />}
        <Divider className={classes.resourcesHeaderDivider} />
      </div>
      <div className={classes.resourceComposition}>
        <ItemComposition
          displayItemsAsLinked
          IconAdd={<AddIcon />}
          addButtonHidden={
            isAddButtonHidden || getOverrideAddButtonVisibility(value)
          }
          addbuttonDisabled={isAddButtonDisabled}
          labelAdd={t(labelAddFilter)}
          onAddItem={addResource}
        >
          {value.map((resource, index) => {
            const forceSingleAutocomplete = checkForceSingleAutocomplete({
              resourceType: resource.resourceType,
              forceSingleAutocompleteConditions
            });

            return (
              <ItemComposition.Item
                className={classes.resourceCompositionItem}
                deleteButtonHidden={
                  deleteButtonHidden ||
                  getResourceStatic(resource.resourceType) ||
                  hideResourceDeleteButton()
                }
                key={`${index}${resource.resourceType}`}
                labelDelete={t(labelDelete)}
                onDeleteItem={deleteResource(index)}
              >
                <SelectField
                  className={classes.resourceType}
                  dataTestId={labelResourceType}
                  disabled={
                    !canEditField ||
                    getDefaultDisabledSelectType(resource.resourceType) ||
                    isValidatingResources ||
                    getResourceStatic(resource.resourceType)
                  }
                  label={t(labelSelectResourceType) as string}
                  options={getResourceTypeOptions(index, resource)}
                  selectedOptionId={getResourceTypeSelectedOptionId(
                    resource.resourceType
                  )}
                  onChange={changeResourceType(index)}
                />
                {singleResourceSelection || forceSingleAutocomplete ? (
                  <SingleConnectedAutocompleteField
                    exclusionOptionProperty="name"
                    changeIdValue={changeIdValue(resource.resourceType)}
                    className={classes.resources}
                    disableClearable={singleResourceSelection}
                    disabled={
                      !canEditField ||
                      isValidatingResources ||
                      (equals(
                        resource.resourceType,
                        defaultResourceTypes?.[1]
                      ) &&
                        !hasSelectedHostForSingleMetricwidget) ||
                      !resource.resourceType
                    }
                    field={getSearchField(resource.resourceType)}
                    getEndpoint={getResourceResourceBaseEndpoint({
                      index,
                      resourceType: resource.resourceType
                    })}
                    label={t(labelSelectAResource)}
                    limitTags={2}
                    queryKey={`${resource.resourceType}-${index}`}
                    value={resource.resources[0] || null}
                    onChange={changeResource(index)}
                  />
                ) : (
                  <MultiConnectedAutocompleteField
                    exclusionOptionProperty="name"
                    changeIdValue={changeIdValue(resource.resourceType)}
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
                    disabled={
                      !canEditField ||
                      isValidatingResources ||
                      !resource.resourceType
                    }
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
            );
          })}
        </ItemComposition>
        {error && <FormHelperText error>{t(error)}</FormHelperText>}
      </div>
    </div>
  );
};

export default Resources;
