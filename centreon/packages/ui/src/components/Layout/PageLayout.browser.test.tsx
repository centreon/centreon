import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { T } from 'ramda';

import { PageHeader } from '..';
import { PageLayout } from '.';
import { AreaIndicator } from './AreaIndicator';

const initialize = (): void => {
  render(
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Menu>
            <PageLayout.QuickAccess
              create={rs.fn()}
              elements={[
                {
                  id: 1,
                  name: 'Entity'
                }
              ]}
              goBack={rs.fn()}
              isActive={T}
              labels={{
                create: 'Create',
                goBack: 'Go back'
              }}
              navigateToElement={rs.fn()}
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
};

describe('Page layout', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize();
  });

  it('displays the page layout', async () => {
    // The header (quick access button, title, description, actions) above
    // the body (actions area, then content), and the quick access menu closed.
    const header = document.querySelector('#header') as HTMLElement;
    const body = document.querySelector('#page-body') as HTMLElement;

    const title = await within(header).findByRole('heading', {
      name: 'page header title'
    });

    expect(title).toBeVisible();
    expect(title).toHaveTextContent(/^Title$/);
    expect(within(header).getByText('Description')).toBeVisible();
    expect(within(header).getByText('Actions')).toBeVisible();
    expect(within(header).getByTestId('quickaccess')).toBeVisible();
    expect(within(header).getByTestId('quickaccess')).toHaveAttribute(
      'data-is-active',
      'false'
    );

    const bodyActions = within(body).getByText('Body actions');
    const content = within(body).getByRole('heading', { name: 'Content' });

    expect(bodyActions).toBeVisible();
    expect(content).toBeVisible();
    expect(header.getBoundingClientRect().bottom).toBeLessThanOrEqual(
      body.getBoundingClientRect().top
    );
    expect(bodyActions.getBoundingClientRect().bottom).toBeLessThanOrEqual(
      content.getBoundingClientRect().top
    );
    expect(screen.queryByText('Entity')).not.toBeInTheDocument();
  });

  it('opens the quick access poppin when the corresponding logo is displayed', async () => {
    await userEvent.click(await screen.findByTestId('quickaccess'));

    expect(await screen.findByText('Entity')).toBeVisible();
    expect(await screen.findByText('Create')).toBeVisible();
    expect(await screen.findByText('Go back')).toBeVisible();

    // The button shown as open, the active element selected, and "Go back"
    // before "Create".
    expect(screen.getByTestId('quickaccess')).toHaveAttribute(
      'data-is-active',
      'true'
    );
    expect(screen.getByRole('menuitem', { name: 'Entity' })).toHaveClass(
      'Mui-selected'
    );
    expect(
      screen.getByRole('button', { name: 'Go back' }).getBoundingClientRect()
        .right
    ).toBeLessThanOrEqual(
      screen.getByRole('button', { name: 'Create' }).getBoundingClientRect()
        .left
    );
  });
});
