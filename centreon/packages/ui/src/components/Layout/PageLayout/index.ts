import { PageLayout as PageLayoutRoot } from './PageLayout';
import { PageLayoutActions } from './PageLayoutActions';
import { PageLayoutBody } from './PageLayoutBody';
import { PageLayoutHeader } from './PageLayoutHeader';
import { PageQuickAccess } from './PageQuickAccess';

export const PageLayout = Object.assign(PageLayoutRoot, {
  Actions: PageLayoutActions,
  Body: PageLayoutBody,
  Header: PageLayoutHeader,
  QuickAccess: PageQuickAccess
});
