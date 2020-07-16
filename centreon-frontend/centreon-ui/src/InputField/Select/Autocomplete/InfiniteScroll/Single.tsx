import InfiniteAutocompleteField from '.';
import AutocompleteField from '..';

const SingleInfiniteAutocompleteField = InfiniteAutocompleteField(
  AutocompleteField,
  false,
);

export default SingleInfiniteAutocompleteField;
