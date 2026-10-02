import Actions from './Hosts.Actions';
import Columns from './Hosts.Columns';
import Filters from './Hosts.Filters';
import Form from './Hosts.Form';
import Listing from './Hosts.Listing';

describe('Hosts configuration: ', () => {
  Listing();
  Columns();
  Filters();
  Actions();
  Form();
});
