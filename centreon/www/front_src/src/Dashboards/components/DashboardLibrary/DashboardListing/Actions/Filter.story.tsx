import { createStore, Provider, useAtomValue } from 'jotai';
import { useMemo } from 'react';

import { searchAtom } from '../atom';
import Filter from './Filter';

// The search is debounced: recording the atom into a hidden input lets the test
// assert the committed value with a retrying web-first assertion.
const RecordedSearch = (): JSX.Element => {
  const search = useAtomValue(searchAtom);

  return (
    <form hidden>
      <input data-testid="search" readOnly value={search} />
    </form>
  );
};

export const Default = (): JSX.Element => {
  const store = useMemo(() => createStore(), []);

  return (
    <Provider store={store}>
      <Filter />
      <RecordedSearch />
    </Provider>
  );
};
