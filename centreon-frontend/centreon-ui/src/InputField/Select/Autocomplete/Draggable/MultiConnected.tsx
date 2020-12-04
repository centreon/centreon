import MultiConnectedAutocompleteField from '../Connected/Multi';

import DraggableAutocompleteField from '.';

const MultiDraggableConnectedAutocompleteField = DraggableAutocompleteField(
  MultiConnectedAutocompleteField,
);

export default MultiDraggableConnectedAutocompleteField;
