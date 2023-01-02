import MultiAutocompleteField from '../../Multi';
import ConnectedAutocompleteField from '..';

const MultiConnectedAutocompleteField = ConnectedAutocompleteField(
  MultiAutocompleteField,
  true
);

export default MultiConnectedAutocompleteField;
