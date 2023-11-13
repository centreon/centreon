import { PageHeader as PageHeaderRoot } from './PageHeader';
import { PageHeaderMain } from './PageHeaderMain';
import { PageHeaderMenu } from './PageHeaderMenu';
import { PageHeaderTitle } from './PageHeaderTitle';
import { PageHeaderActions } from './PageHeaderActions';
import { PageHeaderMessage } from './PageHeaderMessage';

export const PageHeader = Object.assign(PageHeaderRoot, {
  Actions: PageHeaderActions,
  Main: PageHeaderMain,
  Menu: PageHeaderMenu,
  Message: PageHeaderMessage,
  Title: PageHeaderTitle
});
