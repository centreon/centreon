import Form from './AiProviders.Form';
import Listing from './AiProviders.Listing';
import Permissions from './AiProviders.Permissions';

describe('AiProviders', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  Listing();
  Form();
  Permissions();
});
