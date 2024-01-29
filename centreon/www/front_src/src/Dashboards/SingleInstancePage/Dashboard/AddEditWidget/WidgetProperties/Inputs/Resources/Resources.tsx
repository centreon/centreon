/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
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
  labelSelectResourceType
} from '../../../../translatedLabels';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { useResourceStyles } from '../Inputs.styles';
import { areResourcesFullfilled } from '../utils';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import useResources from './useResources';

interface Props {
  propertyName: string;
}

const Resources = ({ propertyName }: Props): JSX.Element => {
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
    deleteResourceItem
  } = useResources(propertyName);

  const { canEditField } = useCanEditProperties();

  const deleteButtonHidden = or(!canEditField, value.length <= 1);

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
              key={`${index}${resource.resources[0]}`}
              labelDelete={t(labelDelete)}
              onDeleteItem={deleteResource(index)}
            >
              <SelectField
                className={classes.resourceType}
                dataTestId={labelResourceType}
                disabled={!canEditField}
                label={t(labelSelectResourceType) as string}
                options={getResourceTypeOptions(resource)}
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
                disabled={!canEditField || !resource.resourceType}
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
    </div>
  );
};

export default Resources;
