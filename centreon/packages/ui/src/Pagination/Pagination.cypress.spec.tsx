import { labelNextPage, labelPreviousPage } from '../Listing/translatedLabels';
import TestQueryProvider from '../api/TestQueryProvider';
import { Method } from '../api/useMutationQuery';
import Pagination from './Pagination';
import { generateItems } from './utils';

const defaultTotalItems = 25;
const itemsPerPage = 6;
const totalPages = Math.ceil(defaultTotalItems / itemsPerPage);

const initialize = ({
  total = defaultTotalItems,
  currentPage = 1
}: { total?: number; currentPage?: number }) => {
  cy.interceptAPIRequest({
    alias: 'list',
    method: Method.GET,
    path: '**/listing**',
    response: {
      result: generateItems(itemsPerPage),
      meta: {
        page: currentPage,
        total,
        limit: itemsPerPage
      }
    }
  });

  cy.mount({
    Component: (
      <div
        style={{
          width: '100%',
          height: '100vh',
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center'
        }}
      >
        <div
          style={{
            height: '176px',
            boxShadow: '2px 2px 4px rgba(0, 0, 0, 0.2)'
          }}
        >
          <TestQueryProvider>
            <Pagination
              api={{ baseEndpoint: '/test/listing', queryKey: ['test'] }}
            />
          </TestQueryProvider>
        </div>
      </div>
    )
  });
};

describe('Pagination Component', () => {
  it('render with correct initial state', () => {
    initialize({});
    cy.waitForRequest('@list');

    cy.findByTestId(labelPreviousPage).should('be.disabled');

    cy.findByTestId(labelNextPage).should('not.be.disabled');

    cy.contains(`Page 1/${totalPages}`);

    cy.makeSnapshot();
  });

  it('hides pagination controls when only one page exists', () => {
    initialize({ total: itemsPerPage });
    cy.waitForRequest('@list');

    cy.findByTestId(labelPreviousPage).should('not.exist');
    cy.findByTestId(labelNextPage).should('not.exist');
    cy.contains(/Page \d+\/\d+/).should('not.exist');

    cy.makeSnapshot();
  });

  it('navigates forward through pages correctly', () => {
    initialize({});
    cy.waitForRequest('@list');

    cy.contains(`Page 1/${totalPages}`);

    Array.from({ length: totalPages - 1 }).forEach((_, index) => {
      cy.findByTestId(labelNextPage).click();
      cy.waitForRequest('@list');

      cy.contains(`Page ${index + 2}/${totalPages}`);
    });

    cy.findByTestId(labelNextPage).should('be.disabled');

    cy.makeSnapshot();
  });

  it('navigates backward through pages correctly', () => {
    initialize({});

    cy.waitForRequest('@list');

    Array.from({ length: totalPages - 1 }).forEach(() => {
      cy.findByTestId(labelNextPage).click();

      cy.waitForRequest('@list');
    });

    Array.from({ length: totalPages - 1 }).forEach((_, index) => {
      cy.findByTestId(labelPreviousPage).click();
      cy.waitForRequest('@list');

      cy.contains(`Page ${totalPages - index - 1}/${totalPages}`);
    });

    cy.findByTestId(labelPreviousPage).should('be.disabled');

    cy.makeSnapshot();
  });

  it('enables both buttons when on middle page', () => {
    initialize({});

    cy.waitForRequest('@list');

    cy.findByTestId(labelNextPage).click();

    cy.waitForRequest('@list');

    cy.findByTestId(labelPreviousPage).should('not.be.disabled');
    cy.findByTestId(labelNextPage).should('not.be.disabled');

    cy.makeSnapshot();
  });
});
