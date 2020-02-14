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
  <MultiSelectEntries label={label} onClick={noOp} emptyLabel={emptyLabel} />
);

export const oneElement = (): JSX.Element => (
  <MultiSelectEntries
    label={label}
    values={[entries[0]]}
    onClick={noOp}
    emptyLabel={emptyLabel}
  />
);

export const oneElementHighlight = (): JSX.Element => (
  <MultiSelectEntries
    label={label}
    values={[entries[0]]}
    onClick={noOp}
    highlight
    emptyLabel={emptyLabel}
  />
);

export const sixElements = (): JSX.Element => (
  <MultiSelectEntries
    label={label}
    values={entries}
    onClick={noOp}
    emptyLabel={emptyLabel}
  />
);

export const sixElementsError = (): JSX.Element => (
  <MultiSelectEntries
    label={label}
    values={entries}
    onClick={noOp}
    emptyLabel={emptyLabel}
    error="Something went wrong"
  />
);
