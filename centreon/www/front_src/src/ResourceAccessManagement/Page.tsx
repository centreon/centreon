import { ReactElement } from 'react';

import { ListingPage } from '@centreon/ui';

import { ResourceAccessRulesListing } from './Listing';
import PageHeader from './PageHeader';
import AddEditResourceAccessRuleModal from './AddEditResourceAccessRule/AddEditResourceAccessRuleModal';
import { DeleteConfirmationDialog } from './Actions/Delete';
import { DuplicationForm } from './Actions/Duplicate';

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
