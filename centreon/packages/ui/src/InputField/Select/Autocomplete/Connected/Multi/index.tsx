import type { ReactElement } from 'react';

import MultiAutocompleteField, {
  type Props as MultiAutocompleteFieldProps
} from '../../Multi';
import ConnectedAutocompleteField, {
  type ConnectedAutoCompleteFieldProps
} from '..';

// The multi field's own props (its chips, its "Select all") on top of the
// connected ones: the connected field is typed with the single field's props.
type MultiConnectedAutocompleteFieldProps<TData> =
  ConnectedAutoCompleteFieldProps<TData> &
    Parameters<ReturnType<typeof ConnectedAutocompleteField>>[0] &
    Pick<MultiAutocompleteFieldProps, 'chipProps' | 'disableSelectAll'>;

const MultiConnectedAutocompleteField = ConnectedAutocompleteField(
  MultiAutocompleteField,
  true
) as <TData extends { name: string }>(
  props: MultiConnectedAutocompleteFieldProps<TData>
) => ReactElement;

export default MultiConnectedAutocompleteField;
