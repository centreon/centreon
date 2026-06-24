import { describe, expect, it, rstest } from '@rstest/core';
import userEvent from '@testing-library/user-event';

import { commentEndpoint } from '../../www/front_src/src/Resources/Actions/api/endpoint';
import AddCommentForm from '../../www/front_src/src/Resources/Graph/Performance/Graph/AddCommentForm';
import type { Resource } from '../../www/front_src/src/Resources/models';
import {
  labelAdd,
  labelComment
} from '../../www/front_src/src/Resources/translatedLabels';
import { renderApp, screen } from './render';
import { interceptApiRequest, waitForRequest } from './server';

/**
 * Phase 0 port of
 * www/front_src/src/Resources/Graph/Performance/Graph/AddCommentForm/AddCommentForm.cypress.spec.tsx
 * to Rstest jsdom: real app component + MSW API interception + outgoing-request
 * assertion (the equivalent of cy.waitForRequest). cy.makeSnapshot is dropped.
 */
const date = new Date('2020-11-26T15:49:39.789Z');

const resource = {
  id: 0,
  parent: { id: 1 },
  type: 'service'
} as Resource;

describe('AddCommentForm (Rstest app POC)', () => {
  it('sends a comment request with the given date and the typed comment', async () => {
    interceptApiRequest({
      alias: 'postComment',
      method: 'post',
      path: commentEndpoint
    });

    renderApp(
      <AddCommentForm
        date={date}
        onClose={rstest.fn()}
        onSuccess={rstest.fn()}
        resource={resource}
      />
    );

    expect(screen.getByLabelText(labelAdd)).toBeDisabled();

    await userEvent.type(screen.getByLabelText(labelComment), 'My Comment');
    await userEvent.click(screen.getByLabelText(labelAdd));

    const { body } = await waitForRequest('postComment');

    expect(body).toEqual({
      resources: [
        {
          ...resource,
          comment: 'My Comment',
          date: '2020-11-26T15:49:39Z'
        }
      ]
    });
  });
});
