import { ReactElement, useCallback, useEffect, useState } from 'react';

import { useField } from 'formik';

import {
  Autocomplete as MuiAutocomplete,
  MenuItem as MuiMenuItem,
  TextField as MuiTextField
} from '@mui/material';

import { useAccessRightsForm } from '../useAccessRightsForm';
import {
  ContactGroupResource,
  ContactResource,
  isContactResource
} from '../AccessRights.resource';
import { GroupLabel } from '../common/GroupLabel';
import { useInputStyles } from '../common/Input.styles';

import { useStyles } from './ContactAccessRightsInput.styles';

export type ContactInputFieldProps = {
  id: string;
  labels: ContactInputFieldLabels;
  name: string;
};

type ContactInputFieldLabels = {
  group: string;
  noOptionsText: string;
  placeholder: string;
};

const ContactInputField = ({
  labels,
  ...props
}: ContactInputFieldProps): ReactElement => {
  const { classes } = useStyles();
  const { classes: inputClasses } = useInputStyles();

  const {
    options: { contacts }
  } = useAccessRightsForm();

  const [field, meta, helpers] = useField(props);

  const [value, setValue] = useState<
    ContactResource | ContactGroupResource | null
  >(meta.initialValue ?? null);

  const onInputChange = useCallback(
    (_, _value) => {
      helpers.setValue(_value);
    },
    [helpers]
  );

  const renderInput = useCallback(
    (params) => (
      <MuiTextField
        {...props}
        {...params}
        placeholder={labels.placeholder}
        size="small"
      />
    ),
    [labels, props]
  );

  useEffect(() => {
    setValue(field.value ?? '');
  }, [field.value]);

  return (
    <MuiAutocomplete<ContactResource | ContactGroupResource, false, false, true>
      autoComplete
      autoHighlight
      openOnFocus
      className={classes.contactInput}
      componentsProps={{
        popper: {
          className: inputClasses.inputDropdown
        }
      }}
      getOptionLabel={(option) =>
        typeof option === 'string' ? option : option.name
      }
      noOptionsText={labels.noOptionsText}
      options={contacts}
      renderInput={renderInput}
      renderOption={(attr, option) => (
        <MuiMenuItem {...attr} key={option.id}>
          {option.name}{' '}
          {!isContactResource(option) && <GroupLabel label={labels.group} />}
        </MuiMenuItem>
      )}
      value={value}
      onChange={onInputChange}
    />
  );
};

export { ContactInputField };
