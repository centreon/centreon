import { PageHeader as PageHeaderRoot } from './PageHeader';
import { PageHeaderActions } from './PageHeaderActions';
import { PageHeaderMain } from './PageHeaderMain';
import { PageHeaderMenu } from './PageHeaderMenu';
import { PageHeaderMessage } from './PageHeaderMessage';
import { PageHeaderTitle } from './PageHeaderTitle';

export const PageHeader = Object.assign(PageHeaderRoot, {
  Actions: PageHeaderActions,
  Main: PageHeaderMain,
  Menu: PageHeaderMenu,
  Message: PageHeaderMessage,
  Title: PageHeaderTitle
});
