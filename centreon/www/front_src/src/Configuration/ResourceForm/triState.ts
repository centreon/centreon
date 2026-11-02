import { JsonDecoder } from 'ts.data.json';

import { labelDefault, labelNo, labelYes } from './translatedLabels';

// The three states as the API spells them. Default is a value of its own: it
// leaves the directive to the template chain, and is never No.
export type TriState = 'true' | 'false' | 'use_default';

export const defaultTriState: TriState = 'use_default';

const triStates: Array<TriState> = ['true', 'false', defaultTriState];

export type TriStateOptions = Array<{ label: string; value: TriState }>;

// Yes / No / Default, for every Yes / No field of the form.
export const triStateOptions: TriStateOptions = [
  { label: labelYes, value: 'true' },
  { label: labelNo, value: 'false' },
  { label: labelDefault, value: defaultTriState }
];

export const triStateDecoder = JsonDecoder.oneOf<TriState>(
  triStates.map((value) => JsonDecoder.isExactly(value)),
  'TriState'
);

// The detail endpoints leave unset values out rather than sending null.
export const optionalTriStateDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(triStateDecoder)
).map((value) => value ?? defaultTriState);
