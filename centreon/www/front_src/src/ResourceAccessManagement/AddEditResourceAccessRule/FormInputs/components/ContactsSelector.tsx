// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Checkbox, FormControlLabel } from '@mui/material';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { Contact, NamedEntity } from '../../../models';
import {
  labelAllContacts,
  labelAllContactsSelected,
  labelContacts
} from '../../../translatedLabels';
import useContactsSelector from '../hooks/useContactsSelector';
import { useSelectorStyles } from '../styles/Selector.styles';

const ContactsSelector = (): React.JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useSelectorStyles();

  const {
    contacts,
    checked,
    deleteContactsItem,
    getEndpoint,
    onCheckboxChange,
    onMultiSelectChange
  } = useContactsSelector();

  return (
    <div className={classes.container}>
      <MultiConnectedAutocompleteField
        allowUniqOption
        chipProps={{
          color: 'primary',
          onDelete: (_: unknown, option: Contact): void =>
            deleteContactsItem({
              contacts,
              option: option as unknown as NamedEntity
            })
        }}
        className={classes.selector}
        dataTestId={labelContacts}
        disabled={checked}
        field="alias"
        getEndpoint={
          getEndpoint() as unknown as (params: {
            page: number;
            search?: unknown;
          }) => string
        }
        getRenderedOptionText={(option): string =>
          (option as unknown as Contact).alias?.toString()
        }
        label={checked ? t(labelAllContactsSelected) : t(labelContacts)}
        limitTags={5}
        onChange={
          onMultiSelectChange() as unknown as (
            event: React.SyntheticEvent,
            value: unknown
          ) => void
        }
        optionProperty="alias"
        value={contacts}
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
        label={t(labelAllContacts)}
      />
    </div>
  );
};

export default ContactsSelector;
