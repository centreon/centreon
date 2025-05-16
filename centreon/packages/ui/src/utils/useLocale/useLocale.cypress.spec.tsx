import { createStore, Provider } from 'jotai';
import { useLocale } from '.';
import { browserLocaleAtom, userAtom } from '@centreon/ui-context';

const TestComponent = () => {
  const locale = useLocale();

  return <p>{locale}</p>;
};

const initialize = ({ userLocale, browserLocale }) => {
  const store = createStore();

  store.set(userAtom, { locale: userLocale });
  store.set(browserLocaleAtom, browserLocale);

  cy.mount({
    Component: (
      <Provider store={store}>
        <TestComponent />
      </Provider>
    )
  });
};

describe('useLocale', () => {
  it('displays the user locale when the corresponding atom is set', () => {
    initialize({ userLocale: 'fi', browserLocale: 'en' });

    cy.contains('fi').should('be.visible');
  });

  it('displays the browser locale when the user locale is not set', () => {
    initialize({ browserLocale: 'de', userLocale: null });

    cy.contains('de').should('be.visible');
  });
});
