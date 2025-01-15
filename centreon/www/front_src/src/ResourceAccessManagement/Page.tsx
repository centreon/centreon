import { ReactElement } from 'react';

import { ListingPage } from '@centreon/ui';

import { DeleteConfirmationDialog } from './Actions/Delete';
import { DuplicationForm } from './Actions/Duplicate';
import AddEditResourceAccessRuleModal from './AddEditResourceAccessRule/AddEditResourceAccessRuleModal';
import { ResourceAccessRulesListing } from './Listing';
import PageHeader from './PageHeader';

const ResourceAccessManagementPage = (): ReactElement => {
  return (
    <>
      <ListingPage
        filter={<PageHeader />}
        listing={<ResourceAccessRulesListing />}
      />
      <AddEditResourceAccessRuleModal />
      <DeleteConfirmationDialog />
      <DuplicationForm />
    </>
  );
};

export default ResourceAccessManagementPage;
