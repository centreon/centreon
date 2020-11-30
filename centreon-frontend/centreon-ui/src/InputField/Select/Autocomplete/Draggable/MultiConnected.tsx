import DraggableAutocompleteField from '.';
import MultiConnectedAutocompleteField from '../Connected/Multi';

const MultiDraggableConnectedAutocompleteField = DraggableAutocompleteField(
  MultiConnectedAutocompleteField,
);

export default MultiDraggableConnectedAutocompleteField;
