import ConnectedAutocompleteField from '..';
import MultiAutocompleteField from '../../Multi';

const MultiConnectedAutocompleteField = ConnectedAutocompleteField(
  MultiAutocompleteField,
  true
);

export default MultiConnectedAutocompleteField;
