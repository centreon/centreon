/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';
import { or } from 'ramda';

import { Divider, FormHelperText, Typography } from '@mui/material';
import FilterIcon from '@mui/icons-material/Tune';

import { Avatar, ItemComposition } from '@centreon/ui/components';
import { MultiConnectedAutocompleteField, SelectField } from '@centreon/ui';

import {
  labelRefineFilter,
  labelDelete,
  labelResourceType,
  labelResources,
  labelSelectAResource,
  labelSelectResourceType,
  labelYouCanChooseOnResourcePerResourceType
} from '../../../../translatedLabels';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { useResourceStyles } from '../Inputs.styles';
import { singleResourceTypeSelectionAtom } from '../../../atoms';
import { areResourcesFullfilled } from '../utils';
import { editProperties } from '../../../../hooks/useCanEditDashboard';

import useResources from './useResources';

interface Props {
  propertyName: string;
}

const Resources = ({ propertyName }: Props): JSX.Element => {
  const { classes } = useResourceStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();
  const { t } = useTranslation();

  const singleResourceTypeSelection = useAtomValue(
    singleResourceTypeSelectionAtom
  );

  const {
    value,
    resourceTypeOptions,
    changeResourceType,
    addResource,
    deleteResource,
    changeResources,
    getResourceResourceBaseEndpoint,
    getSearchField,
    error,
    getOptionDisabled,
    deleteResourceItem
  } = useResources(propertyName);

  const { canEditField } = editProperties.useCanEditProperties();

  const deleteButtonHidden = or(!canEditField, value.length <= 1);

  return (
    <div className={classes.resourcesContainer}>
      <div className={classes.resourcesHeader}>
        <Avatar compact className={avatarClasses.widgetAvatar}>
          2
        </Avatar>
        <Typography>{t(labelResources)}</Typography>
        <Divider className={classes.resourcesHeaderDivider} />
      </div>
      <div className={classes.resourceComposition}>
        <ItemComposition
          IconAdd={<FilterIcon />}
          addButtonHidden={!canEditField}
          addbuttonDisabled={!areResourcesFullfilled(value)}
          labelAdd={t(labelRefineFilter)}
          onAddItem={addResource}
        >
          {value.map((resource, index) => (
            <ItemComposition.Item
              className={classes.resourceCompositionItem}
              deleteButtonHidden={deleteButtonHidden}
              key={`${index}`}
              labelDelete={t(labelDelete)}
              onDeleteItem={deleteResource(index)}
            >
              <SelectField
                className={classes.resourceType}
                dataTestId={labelResourceType}
                disabled={!canEditField}
                label={t(labelSelectResourceType) as string}
                options={resourceTypeOptions}
                selectedOptionId={resource.resourceType}
                onChange={changeResourceType(index)}
              />
              <MultiConnectedAutocompleteField
                allowUniqOption
                get
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
                getEndpoint={getResourceResourceBaseEndpoint(
                  resource.resourceType
                )}
                getOptionDisabled={getOptionDisabled(index)}
                label={t(labelSelectAResource)}
                limitTags={2}
                queryKey={`${resource.resourceType}-${index}`}
                value={resource.resources || []}
                onChange={changeResources(index)}
              />
            </ItemComposition.Item>
          ))}
        </ItemComposition>
        {singleResourceTypeSelection && (
          <Typography sx={{ color: 'action.disabled' }}>
            {t(labelYouCanChooseOnResourcePerResourceType)}
          </Typography>
        )}
        {error && <FormHelperText error>{t(error)}</FormHelperText>}
      </div>
    </div>
  );
};

export default Resources;
