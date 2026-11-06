import Filters from './HostGroups.Filters';
import Listing from './HostGroups.Listing';
import Modal from './HostGroups.Modal';

describe('Host groups configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Filters();
  Modal();
});
