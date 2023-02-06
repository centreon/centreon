import { atomWithLocalStorage } from '@centreon/ui';

export const detailsCardsAtom = atomWithLocalStorage<Array<string>>(
  'centreon-resource-status-details-card-21.10',
  []
);
