import { Method } from '@centreon/ui';

import { commentEndpoint } from '../../../../Actions/api/endpoint';
import { Resource } from '../../../../models';
import { labelAdd, labelComment } from '../../../../translatedLabels';

import AddCommentForm from '.';

const date = new Date('2020-11-26T15:49:39.789Z');

const resource = {
  id: 0,
  parent: {
    id: 1
  },
  type: 'service'
} as Resource;

describe('Add comment form', () => {
  beforeEach(() => {
    const onSuccess = cy.stub();
    const onClose = cy.stub();

    cy.interceptAPIRequest({
      alias: 'postComment',
      method: Method.POST,
      path: commentEndpoint
    });

    cy.mount({
      Component: (
        <AddCommentForm
          date={date}
          resource={resource}
          onClose={onClose}
          onSuccess={onSuccess}
        />
      )
    });
  });

  it('sends a comment request with the given date and the typed comment', () => {
    cy.findByLabelText(labelAdd).should('be.disabled');

    cy.findByLabelText(labelComment).type('My Comment');

    cy.findByLabelText(labelAdd).click();

    cy.waitForRequest('@postComment').then(({ request }) => {
      const commentParameters = {
        comment: 'My Comment',
        date: '2020-11-26T15:49:39Z'
      };

      expect(request.body).to.deep.equal({
        resources: [
          {
            ...resource,
            ...commentParameters
          }
        ]
      });
    });

    cy.makeSnapshot();
  });
});
