import { useTranslation } from 'react-i18next';
import { useResourceStyles } from '../Inputs.styles';
import {
  labelActivateRegex,
  labelSelectAResource
} from '../../../../translatedLabels';
import {
  MultiConnectedAutocompleteField,
  RegexIcon,
  SingleConnectedAutocompleteField
} from '@centreon/ui';
import { ReactElement } from 'react';
import { WidgetDataResource } from '../../../models';
import { UseResourcesState } from './useResources';
import { IconButton, Tooltip } from '@centreon/ui/components';
import RegexField from './RegexField';

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
        changeRegexFieldOnResourceType={changeRegexFieldOnResourceType({
          resourceType: resource.resourceType,
          index
        })}
        resourceType={resource.resourceType}
        changeRegexField={changeRegexField(index)}
        value={resource.resources}
      />
    );
  }

  const endAdornment = allowRegex ? (
    <Tooltip label={t(labelActivateRegex)}>
      <IconButton
        className={classes.regexIcon}
        onClick={changeRegexFieldOnResourceType({
          resourceType: resource.resourceType,
          index
        })}
        size="small"
        icon={<RegexIcon />}
      />
    </Tooltip>
  ) : undefined;

  if (singleResourceSelection) {
    return (
      <SingleConnectedAutocompleteField
        exclusionOptionProperty="name"
        changeIdValue={changeIdValue(resource.resourceType)}
        className={classes.resources}
        disableClearable={singleResourceSelection}
        disabled={disabled}
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
        endAdornment={endAdornment}
      />
    );
  }

  return (
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
      disabled={disabled}
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
      endAdornment={endAdornment}
    />
  );
};

export default ResourceField;
