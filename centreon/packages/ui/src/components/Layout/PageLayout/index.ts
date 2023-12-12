import { PageLayout as PageLayoutRoot } from './PageLayout';
import { PageLayoutHeader } from './PageLayoutHeader';
import { PageLayoutBody } from './PageLayoutBody';
import { PageLayoutActions } from './PageLayoutActions';
import { PageQuickAccess } from './PageQuickAccess';

export const PageLayout = Object.assign(PageLayoutRoot, {
  Actions: PageLayoutActions,
  Body: PageLayoutBody,
  Header: PageLayoutHeader,
  QuickAccess: PageQuickAccess
});
