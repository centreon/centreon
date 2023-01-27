import AutocompleteField from '..';

import ConnectedAutocompleteField from '.';

const SingleConnectedAutocompleteField = ConnectedAutocompleteField(
  AutocompleteField,
  false
);

export default SingleConnectedAutocompleteField;
