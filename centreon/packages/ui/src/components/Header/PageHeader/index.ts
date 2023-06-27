import { PageHeader as PageHeaderRoot } from './PageHeader';
import { PageHeaderMain } from './PageHeaderMain';
import { PageHeaderMenu } from './PageHeaderMenu';
import { PageHeaderTitle } from './PageHeaderTitle';
import { PageHeaderActions } from './PageHeaderActions';

export const PageHeader = Object.assign(PageHeaderRoot, {
  Actions: PageHeaderActions,
  Main: PageHeaderMain,
  Menu: PageHeaderMenu,
  Title: PageHeaderTitle
});
