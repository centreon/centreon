// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Checkbox, FormControlLabel } from '@mui/material';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { NamedEntity } from '../../../models';
import {
  labelAllContactGroups,
  labelAllContactGroupsSelected,
  labelContactGroups
} from '../../../translatedLabels';
import useContactGroupsSelector from '../hooks/useContactGroupsSelector';
import { useSelectorStyles } from '../styles/Selector.styles';

const ContactGroupsSelector = (): React.JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useSelectorStyles();

  const {
    contactGroups,
    checked,
    deleteContactGroupsItem,
    getEndpoint,
    onCheckboxChange,
    onMultiSelectChange
  } = useContactGroupsSelector();

  return (
    <div className={classes.container}>
      <MultiConnectedAutocompleteField
        allowUniqOption
        chipProps={{
          color: 'primary',
          onDelete: (_: unknown, option: NamedEntity): void =>
            deleteContactGroupsItem({ contactGroups, option })
        }}
        className={classes.selector}
        dataTestId={labelContactGroups}
        disabled={checked}
        field="name"
        getEndpoint={
          getEndpoint() as unknown as (params: {
            page: number;
            search?: unknown;
          }) => string
        }
        label={
          checked ? t(labelAllContactGroupsSelected) : t(labelContactGroups)
        }
        limitTags={5}
        onChange={
          onMultiSelectChange() as unknown as (
            event: React.SyntheticEvent,
            value: unknown
          ) => void
        }
        value={contactGroups}
      />
      <FormControlLabel
        className={classes.label}
        control={
          <Checkbox
            checked={checked}
            className={classes.checkbox}
            onChange={onCheckboxChange}
            size="small"
          />
        }
        label={t(labelAllContactGroups)}
      />
    </div>
  );
};

export default ContactGroupsSelector;
