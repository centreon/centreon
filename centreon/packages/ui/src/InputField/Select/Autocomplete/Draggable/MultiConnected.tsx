import MultiAutocompleteField from '../Multi';
import ConnectedAutocompleteField from '../Connected';

import DraggableAutocompleteField from '.';

const MultiConnectedAutocompleteField = ConnectedAutocompleteField(
  MultiAutocompleteField,
  false
);

const MultiDraggableConnectedAutocompleteField = DraggableAutocompleteField(
  MultiConnectedAutocompleteField
);

export default MultiDraggableConnectedAutocompleteField;
