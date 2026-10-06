import { JsonDecoder } from 'ts.data.json';

import { labelDefault, labelNo, labelYes } from '../translatedLabels';

// The three states as the API spells them. Default is a value of its own: it
// leaves the directive to the template chain, and is never No.
export type TriState = 'true' | 'false' | 'use_default';

export const defaultTriState: TriState = 'use_default';

export const triStateOptions: Array<{ label: string; value: TriState }> = [
  { label: labelYes, value: 'true' },
  { label: labelNo, value: 'false' },
  { label: labelDefault, value: defaultTriState }
];

export const triStateDecoder = JsonDecoder.oneOf<TriState>(
  triStateOptions.map(({ value }) => JsonDecoder.isExactly(value)),
  'TriState'
);
