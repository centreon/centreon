import ConnectedAutocompleteField from '.';
import AutocompleteField from '..';

const SingleConnectedAutocompleteField = ConnectedAutocompleteField(
  AutocompleteField,
  false,
);

export default SingleConnectedAutocompleteField;
