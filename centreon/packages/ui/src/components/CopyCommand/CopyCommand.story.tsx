import { ThemeMode, userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';

import SnackbarProvider from '../../Snackbar/SnackbarProvider';
import ThemeProvider from '../../ThemeProvider';
import CopyCommand, { type CopyCommandProps } from './CopyCommand';

interface Props extends CopyCommandProps {
  dark?: boolean;
}

// The theme of the user, not the emulated color scheme, colors the code.
export const Default = ({ dark = false, ...props }: Props): JSX.Element => {
  const store = createStore();
  store.set(userAtom, {
    themeMode: dark ? ThemeMode.dark : ThemeMode.light
  } as never);

  return (
    <Provider store={store}>
      <ThemeProvider>
        <SnackbarProvider>
          <CopyCommand {...props} />
        </SnackbarProvider>
      </ThemeProvider>
    </Provider>
  );
};
