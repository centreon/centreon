import { browserLocaleAtom, type User, userAtom } from '@centreon/ui-context';

import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';
import { createStore, Provider } from 'jotai';

import { useLocale } from '.';

const TestComponent = () => {
  const locale = useLocale();

  return <p>{locale}</p>;
};

interface InitializeProps {
  browserLocale: string;
  userLocale: string | null;
}

const initialize = ({ userLocale, browserLocale }: InitializeProps): void => {
  const store = createStore();

  store.set(userAtom, { locale: userLocale } as User);
  store.set(browserLocaleAtom, browserLocale);

  render(
    <Provider store={store}>
      <TestComponent />
    </Provider>
  );
};

describe('useLocale', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the user locale when the corresponding atom is set', async () => {
    initialize({ browserLocale: 'en', userLocale: 'fi' });

    expect(await screen.findByText('fi')).toBeVisible();
  });

  it('displays the browser locale when the user locale is not set', async () => {
    initialize({ browserLocale: 'de', userLocale: null });

    expect(await screen.findByText('de')).toBeVisible();
  });
});
