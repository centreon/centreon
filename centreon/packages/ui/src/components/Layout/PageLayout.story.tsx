import { T } from 'ramda';

import { PageHeader } from '..';
import { PageLayout } from '.';
import { AreaIndicator } from './AreaIndicator';

const noop = (): void => undefined;

export const Default = (): JSX.Element => (
  <PageLayout>
    <PageLayout.Header>
      <PageHeader>
        <PageHeader.Menu>
          <PageLayout.QuickAccess
            create={noop}
            elements={[{ id: 1, name: 'Entity' }]}
            goBack={noop}
            isActive={T}
            labels={{ create: 'Create', goBack: 'Go back' }}
            navigateToElement={() => noop}
          />
        </PageHeader.Menu>
        <PageHeader.Main>
          <PageHeader.Title description="Description" title="Title" />
        </PageHeader.Main>
        <PageHeader.Actions>Actions</PageHeader.Actions>
      </PageHeader>
    </PageLayout.Header>
    <PageLayout.Body>
      <PageLayout.Actions>
        <AreaIndicator name="Body actions" />
      </PageLayout.Actions>
      <h1>Content</h1>
    </PageLayout.Body>
  </PageLayout>
);
