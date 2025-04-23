import ConnectedAutocompleteField from '../Connected';
import MultiAutocompleteField from '../Multi';

import DraggableAutocompleteField from '.';

const MultiConnectedAutocompleteField = ConnectedAutocompleteField(
  MultiAutocompleteField,
  false
);

const MultiDraggableConnectedAutocompleteField = DraggableAutocompleteField(
  MultiConnectedAutocompleteField
);

export default MultiDraggableConnectedAutocompleteField;
