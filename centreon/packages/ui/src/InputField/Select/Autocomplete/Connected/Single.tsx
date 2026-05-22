import type { ReactElement } from 'react';

import AutocompleteField from '..';
import ConnectedAutocompleteField from '.';

const SingleConnectedAutocompleteField = ConnectedAutocompleteField(
  AutocompleteField as unknown as (props: unknown) => ReactElement,
  false
);

export default SingleConnectedAutocompleteField;
