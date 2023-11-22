import { ListingPage } from '@centreon/ui';

import { ResourceAccessRulesListing } from './Listing';
import PageHeader from './PageHeader';
import ResourceAccessRuleConfigModal from './Modal/ResourceAccessRuleConfigModal';

const ResourceAccessManagementPage = (): JSX.Element => {
  return (
    <>
      <ListingPage
        filter={<PageHeader />}
        listing={<ResourceAccessRulesListing />}
      />
      <ResourceAccessRuleConfigModal />
    </>
  );
};

export default ResourceAccessManagementPage;
