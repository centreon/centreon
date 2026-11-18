import type { ResourceRow } from '../../models';

// Owned here rather than derived from a label: Pendo runs with
// `excludeAllText`, so it tags these elements by their test id alone, and a
// wording change must not move the tag on every page built on this listing.
export const listingDataTestIds = {
  massive: {
    delete: 'listing-massive-delete',
    disable: 'listing-massive-disable',
    duplicate: 'listing-massive-duplicate',
    enable: 'listing-massive-enable'
  },
  moreActions: 'listing-more-actions',
  row: {
    delete: (id: ResourceRow['id']): string => `listing-action-delete_${id}`,
    duplicate: (id: ResourceRow['id']): string =>
      `listing-action-duplicate_${id}`,
    enableDisable: (id: ResourceRow['id']): string =>
      `listing-action-enable-disable_${id}`
  }
};
