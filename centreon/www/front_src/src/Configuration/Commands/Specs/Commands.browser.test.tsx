import Filters from './Commands.Filters';
import Listing from './Commands.Listing';
import Modal from './Commands.Modal';

describe('Commands configuration: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Modal();
  Filters();
});
