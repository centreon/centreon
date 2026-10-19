import { browserLocaleAtom, userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';

import { useLocale } from '.';

interface Props {
  browserLocale: string;
  userLocale: string | null;
}

const Locale = (): JSX.Element => <p>{useLocale()}</p>;

export const Default = ({ browserLocale, userLocale }: Props): JSX.Element => {
  const store = createStore();
  store.set(userAtom, { locale: userLocale } as never);
  store.set(browserLocaleAtom, browserLocale);

  return (
    <Provider store={store}>
      <Locale />
    </Provider>
  );
};
