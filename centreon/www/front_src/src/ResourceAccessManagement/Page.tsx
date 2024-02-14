import { ReactElement } from 'react';

import { ListingPage } from '@centreon/ui';

import { ResourceAccessRulesListing } from './Listing';
import PageHeader from './PageHeader';
import AddEditResourceAccessRuleModal from './AddEditResourceAccessRule/AddEditResourceAccessRuleModal';

const ResourceAccessManagementPage = (): ReactElement => {
  return (
    <>
      <ListingPage
        filter={<PageHeader />}
        listing={<ResourceAccessRulesListing />}
      />
      <AddEditResourceAccessRuleModal />
    </>
  );
};

export default ResourceAccessManagementPage;
