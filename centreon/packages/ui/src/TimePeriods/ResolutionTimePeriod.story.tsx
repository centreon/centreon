import { Switch } from '@mui/material';

import { userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';

import TimePeriod from './index';
import { retrievedUser } from './mockedData';

export const Default = (): JSX.Element => {
  const store = createStore();
  store.set(userAtom, {
    ...retrievedUser,
    locale: 'en_US',
    timezone: 'Europe/Paris'
  } as never);

  return (
    <Provider store={store}>
      <TimePeriod renderExternalComponent={<Switch />} />
    </Provider>
  );
};
