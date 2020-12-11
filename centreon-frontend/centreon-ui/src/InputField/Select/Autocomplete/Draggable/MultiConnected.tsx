import DraggableAutocompleteField from '.';

import MultiAutocompleteField from '../Multi';

import ConnectedAutocompleteField from '../Connected';

const MultiConnectedAutocompleteField = ConnectedAutocompleteField(
  MultiAutocompleteField,
  false,
);

const MultiDraggableConnectedAutocompleteField = DraggableAutocompleteField(
  MultiConnectedAutocompleteField,
);

export default MultiDraggableConnectedAutocompleteField;
