import InfiniteAutocompleteField from '.';
import MultiAutocompleteField from '../Multi';

const MultiInfiniteAutocompleteField = InfiniteAutocompleteField(
  MultiAutocompleteField,
  true,
);

export default MultiInfiniteAutocompleteField;
