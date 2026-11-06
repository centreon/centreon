import Filters from './Acc.Filters';
import Listing from './Acc.Listing';
import Modal from './Acc.Modal';

describe('Additional connectors configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Filters();
  Modal();
});
