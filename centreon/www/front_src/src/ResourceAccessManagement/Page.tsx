import { ListingPage } from '@centreon/ui';

import { ResourceAccessRulesListing } from './Listing';
import PageHeader from './PageHeader';

const ResourceAccessManagementPage = (): JSX.Element => {
  return (
    <ListingPage
      filter={<PageHeader />}
      listing={<ResourceAccessRulesListing />}
    />
  );
};

export default ResourceAccessManagementPage;
