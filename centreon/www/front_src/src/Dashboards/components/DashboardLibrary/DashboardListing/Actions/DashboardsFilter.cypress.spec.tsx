import { Provider, createStore } from 'jotai';
import { searchAtom } from '../atom';
import Filter from './Filter';
import { labelClearFilter } from '../translatedLabels';

const store = createStore();

describe('Filter Component', () => {
  store.set(searchAtom, '');

  beforeEach(() => {
    cy.mount({
      Component: (
        <Provider store={store}>
          <Filter />
        </Provider>
      )
    });
  });

  it('should render the search input with a placeholder', () => {
    cy.get('input')
      .should('be.visible')
      .should('have.attr', 'placeholder', 'Search');
  });

  it('should clear the filter when clicking the clear button', () => {
    const typedText = 'Dashboard 1';

    cy.get('input').type(typedText);
    cy.get('input').should('have.value', typedText);

    cy.findByTestId(labelClearFilter).click();

    cy.get('input').should('have.value', '');
  });
});
