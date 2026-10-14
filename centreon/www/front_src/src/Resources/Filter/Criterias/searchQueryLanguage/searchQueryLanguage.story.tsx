import { useEffect } from 'react';

import { labelSoft } from '../../../translatedLabels';
import { selectableResourceTypes, selectableStatuses } from '../models';
import { build, getAutocompleteSuggestions, parse } from '.';

const api = {
  build,
  getAutocompleteSuggestions,
  labelSoft,
  parse,
  resourceTypeIds: selectableResourceTypes.map(({ id }) => id),
  statusIds: selectableStatuses.map(({ id }) => `${id}`)
};

declare global {
  interface Window {
    searchQueryLanguage?: typeof api;
  }
}

// Pure functions whose module chain needs a browser (@centreon/ui reads
// document at import): exposed on window for page.evaluate().
export const Default = (): JSX.Element => {
  useEffect(() => {
    window.searchQueryLanguage = api;
  }, []);

  return <p>search query language ready</p>;
};
