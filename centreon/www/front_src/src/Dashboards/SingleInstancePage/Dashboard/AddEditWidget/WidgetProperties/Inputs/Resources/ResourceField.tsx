import {
  MultiConnectedAutocompleteField,
  RegexIcon,
  SingleConnectedAutocompleteField
} from '@centreon/ui';
import { IconButton, Tooltip } from '@centreon/ui/components';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelActivateRegex,
  labelSelectAResource
} from '../../../../translatedLabels';
import { WidgetDataResource } from '../../../models';
import { useResourceStyles } from '../Inputs.styles';
import RegexField from './RegexField';
import { UseResourcesState } from './useResources';

interface Props
  extends Pick<
    UseResourcesState,
    | 'singleResourceSelection'
    | 'changeIdValue'
    | 'changeResource'
    | 'changeResources'
    | 'deleteResourceItem'
    | 'getSearchField'
    | 'getResourceResourceBaseEndpoint'
    | 'changeRegexFieldOnResourceType'
    | 'changeRegexField'
  > {
  disabled: boolean;
  allowRegex: boolean;
  isRegexField: boolean;
  resource: WidgetDataResource;
  index: number;
}

const ResourceField = ({
  disabled,
  singleResourceSelection,
  allowRegex,
  isRegexField,
  resource,
  changeIdValue,
  getSearchField,
  getResourceResourceBaseEndpoint,
  changeResource,
  index,
  changeResources,
  deleteResourceItem,
  changeRegexFieldOnResourceType,
  changeRegexField
}: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useResourceStyles();

  if (allowRegex && isRegexField) {
    return (
      <RegexField
        changeRegexField={changeRegexField(index)}
        changeRegexFieldOnResourceType={changeRegexFieldOnResourceType({
          index,
          resourceType: resource.resourceType
        })}
        resourceType={resource.resourceType}
        value={resource.resources}
      />
    );
  }

  const endAdornment = allowRegex ? (
    <Tooltip label={t(labelActivateRegex)}>
      <IconButton
        className={classes.regexIcon}
        data-testid={`${labelActivateRegex}-${resource.resourceType}`}
        icon={<RegexIcon />}
        onClick={changeRegexFieldOnResourceType({
          index,
          resourceType: resource.resourceType
        })}
        size="small"
      />
    </Tooltip>
  ) : undefined;

  if (singleResourceSelection) {
    return (
      <SingleConnectedAutocompleteField
        changeIdValue={changeIdValue(resource.resourceType)}
        className={classes.resources}
        disableClearable={singleResourceSelection}
        disabled={disabled}
        endAdornment={endAdornment}
        exclusionOptionProperty="name"
        field={getSearchField(resource.resourceType)}
        getEndpoint={getResourceResourceBaseEndpoint({
          index,
          resourceType: resource.resourceType
        })}
        label={t(labelSelectAResource)}
        limitTags={2}
        onChange={changeResource(index)}
        queryKey={`${resource.resourceType}-${index}`}
        value={resource.resources[0] || null}
      />
    );
  }

  return (
    <MultiConnectedAutocompleteField
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
      disabled={disabled}
      endAdornment={endAdornment}
      exclusionOptionProperty="name"
      field={getSearchField(resource.resourceType)}
      getEndpoint={getResourceResourceBaseEndpoint({
        index,
        resourceType: resource.resourceType
      })}
      label={t(labelSelectAResource)}
      limitTags={2}
      onChange={changeResources(index)}
      placeholder=""
      queryKey={`${resource.resourceType}-${index}`}
      value={resource.resources || []}
    />
  );
};

export default ResourceField;
