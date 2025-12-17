import AddIcon from '@mui/icons-material/Add';
import {
  CircularProgress,
  Divider,
  FormHelperText,
  Typography
} from '@mui/material';

import { SelectField } from '@centreon/ui';
import { Avatar, ItemComposition } from '@centreon/ui/components';

import { equals, isNil } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import {
  labelAddFilter,
  labelDelete,
  labelResources,
  labelResourceType,
  labelSelectResourceType
} from '../../../../translatedLabels';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { WidgetPropertyProps, WidgetResourceType } from '../../../models';
import { useResourceStyles } from '../Inputs.styles';
import { areResourcesFullfilled } from '../utils';
import ConfirmationResourceTypeToggleRegexModal from './ConfirmationResourceTypeToggleRegexModal';
import ResourceField from './ResourceField';
import useDefaultSelectTypeData from './useDefaultSelectType';
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
  forceSingleAutocompleteConditions,
  allowRegexOnResourceTypes
}: WidgetPropertyProps): ReactElement => {
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
    checkForceSingleAutocomplete,
    getIsRegexAllowedOnResourceType,
    getIsRegexFieldOnResourceType,
    changeRegexFieldOnResourceType,
    changeRegexField
  } = useResources({
    excludedResourceTypes,
    propertyName,
    required,
    restrictedResourceTypes,
    useAdditionalResources,
    forcedResourceType,
    defaultResourceTypes,
    allowRegexOnResourceTypes
  });

  const { getDefaultDisabledSelectType, getDefaultRequiredSelectType } =
    useDefaultSelectTypeData({ value, selectType });

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
          addButtonHidden={isAddButtonHidden}
          onAddItem={addResource}
          addbuttonDisabled={isAddButtonDisabled}
          labelAdd={t(labelAddFilter)}
        >
          {value.map((resource, index) => {
            const forceSingleAutocomplete = checkForceSingleAutocomplete({
              resourceType: resource.resourceType,
              forceSingleAutocompleteConditions
            });

            const allowRegex = getIsRegexAllowedOnResourceType(
              resource.resourceType
            );
            const isRegexField = getIsRegexFieldOnResourceType(
              resource.resourceType
            );

            return (
              <ItemComposition.Item
                className={classes.resourceCompositionItem}
                deleteButtonHidden={
                  deleteButtonHidden ||
                  getResourceStatic(resource.resourceType) ||
                  hideResourceDeleteButton() ||
                  getDefaultRequiredSelectType(resource.resourceType)
                }
                key={`${index}${resource.resourceType}`}
                labelDelete={t(labelDelete)}
                onDeleteItem={deleteResource(index)}
              >
                <SelectField
                  formControlProps={{
                    required: getDefaultRequiredSelectType(
                      resource.resourceType
                    )
                  }}
                  className={classes.resourceType}
                  dataTestId={labelResourceType}
                  disabled={
                    !canEditField ||
                    isValidatingResources ||
                    getResourceStatic(resource.resourceType) ||
                    getDefaultDisabledSelectType(resource.resourceType)
                  }
                  label={t(labelSelectResourceType) as string}
                  options={getResourceTypeOptions(index, resource)}
                  selectedOptionId={getResourceTypeSelectedOptionId(
                    resource.resourceType
                  )}
                  onChange={changeResourceType(index)}
                />
                <ResourceField
                  singleResourceSelection={
                    singleResourceSelection || forceSingleAutocomplete
                  }
                  disabled={
                    singleResourceSelection || forceSingleAutocomplete
                      ? !canEditField ||
                        isValidatingResources ||
                        (equals(
                          resource.resourceType,
                          defaultResourceTypes?.[1]
                        ) &&
                          !hasSelectedHostForSingleMetricwidget) ||
                        !resource.resourceType ||
                        getDefaultDisabledSelectType(resource.resourceType)
                      : !canEditField ||
                        isValidatingResources ||
                        !resource.resourceType ||
                        getDefaultDisabledSelectType(resource.resourceType)
                  }
                  resource={resource}
                  index={index}
                  allowRegex={allowRegex}
                  isRegexField={isRegexField}
                  changeIdValue={changeIdValue}
                  changeResource={changeResource}
                  changeResources={changeResources}
                  getSearchField={getSearchField}
                  getResourceResourceBaseEndpoint={
                    getResourceResourceBaseEndpoint
                  }
                  deleteResourceItem={deleteResourceItem}
                  changeRegexFieldOnResourceType={
                    changeRegexFieldOnResourceType
                  }
                  changeRegexField={changeRegexField}
                />
              </ItemComposition.Item>
            );
          })}
        </ItemComposition>
        {error && <FormHelperText error>{t(error)}</FormHelperText>}
      </div>
      <ConfirmationResourceTypeToggleRegexModal
        changeRegexFieldOnResourceType={changeRegexFieldOnResourceType}
      />
    </div>
  );
};

export default Resources;
