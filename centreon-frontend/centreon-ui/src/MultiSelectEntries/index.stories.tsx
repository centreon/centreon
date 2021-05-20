import React from 'react';

import MultiSelectEntries from '.';

export default { title: 'MultiSelectEntries' };

const label = 'Entries';
const emptyLabel = 'Click to add Entries';

const sixElement = new Array(6).fill(0);

const entries = [...sixElement].map((_, index) => ({
  id: index,
  name: `Entry ${index}`,
}));

const noOp = (): void => undefined;

export const empty = (): JSX.Element => (
  <MultiSelectEntries emptyLabel={emptyLabel} label={label} onClick={noOp} />
);

export const oneElement = (): JSX.Element => (
  <MultiSelectEntries
    emptyLabel={emptyLabel}
    label={label}
    values={[entries[0]]}
    onClick={noOp}
  />
);

export const oneElementHighlight = (): JSX.Element => (
  <MultiSelectEntries
    highlight
    emptyLabel={emptyLabel}
    label={label}
    values={[entries[0]]}
    onClick={noOp}
  />
);

export const sixElements = (): JSX.Element => (
  <MultiSelectEntries
    emptyLabel={emptyLabel}
    label={label}
    values={entries}
    onClick={noOp}
  />
);

export const sixElementsError = (): JSX.Element => (
  <MultiSelectEntries
    emptyLabel={emptyLabel}
    error="Something went wrong"
    label={label}
    values={entries}
    onClick={noOp}
  />
);
