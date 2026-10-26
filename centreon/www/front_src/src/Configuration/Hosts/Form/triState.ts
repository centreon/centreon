import { JsonDecoder } from 'ts.data.json';

import {
  labelDefault,
  labelInherit,
  labelInheritFromTemplate,
  labelNo,
  labelYes
} from '../translatedLabels';

// The three states as the API spells them. Default is a value of its own: it
// leaves the directive to the template chain, and is never No.
export type TriState = 'true' | 'false' | 'use_default';

export const defaultTriState: TriState = 'use_default';

const triStates: Array<TriState> = ['true', 'false', defaultTriState];

type TriStateOptions = Array<{ label: string; value: TriState }>;

const getTriStateOptions = (defaultLabel: string): TriStateOptions => [
  { label: labelYes, value: 'true' },
  { label: labelNo, value: 'false' },
  { label: defaultLabel, value: defaultTriState }
];

// The same three states: the design words the third one per field.
export const triStateOptions: Record<
  'default' | 'inherit' | 'inheritFromTemplate',
  TriStateOptions
> = {
  default: getTriStateOptions(labelDefault),
  inherit: getTriStateOptions(labelInherit),
  inheritFromTemplate: getTriStateOptions(labelInheritFromTemplate)
};

export const triStateDecoder = JsonDecoder.oneOf<TriState>(
  triStates.map((value) => JsonDecoder.isExactly(value)),
  'TriState'
);
