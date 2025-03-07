import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router';

import { federatedModulesAtom } from '@centreon/ui-context';
import {
  RenderResult,
  render,
  screen,
  waitFor
} from '@centreon/ui/test/testRenderer';

import { labelYouAreNotAllowedToSeeThisPage } from '../../FallbackPages/NotAllowedPage/translatedLabels';
import { labelThisPageCouldNotBeFound } from '../../FallbackPages/NotFoundPage/translatedLabels';
import {
  retrievedNavigation,
  retrievedNavigationWithAnEmptySet
} from '../../Navigation/mocks';
import navigationAtom from '../../Navigation/navigationAtoms';
import { retrievedFederatedModule } from '../../federatedModules/mocks';

import { TextDecoder, TextEncoder } from 'node:util';
import ReactRouter from '.';

Object.assign(global, { TextDecoder, TextEncoder });

const labelResourceStatus = 'Resource Status page';

jest.mock('../../Resources', () => {
  const Resources = (): JSX.Element => <p>{labelResourceStatus}</p>;

  return {
    __esModule: true,
    default: Resources
  };
});

const renderReactRouter = (navigation = retrievedNavigation): RenderResult => {
  const store = createStore();
  store.set(navigationAtom, navigation);
  store.set(federatedModulesAtom, [retrievedFederatedModule]);

  return render(
    <BrowserRouter>
      <Provider store={store}>
        <ReactRouter />
      </Provider>
    </BrowserRouter>
  );
};

describe('React Router', () => {
  afterEach(() => {
    window.history.pushState({}, '', '/');
  });

  it('displays the page when the page exists and the user is allowed', async () => {
    window.history.pushState({}, '', '/monitoring/resources');

    renderReactRouter();

    await waitFor(() => {
      expect(screen.getByText(labelResourceStatus)).toBeInTheDocument();
    });
  });

  it('displays the fallback page with an error message when the page is not found', async () => {
    window.history.pushState({}, '', '/not-found');

    renderReactRouter();

    await waitFor(() => {
      expect(
        screen.getByText(labelThisPageCouldNotBeFound)
      ).toBeInTheDocument();
    });

    expect(screen.getByText('404')).toBeInTheDocument();
    expect(
      screen.getByText('This page could not be found')
    ).toBeInTheDocument();
  });

  it('displays the fallback page with an error message when the user is not allowed', async () => {
    window.history.pushState({}, '', '/monitoring/resources');

    renderReactRouter(retrievedNavigationWithAnEmptySet);

    await waitFor(() => {
      expect(
        screen.getByText(labelYouAreNotAllowedToSeeThisPage)
      ).toBeInTheDocument();
    });

    expect(screen.getByText('Lost in space?')).toBeInTheDocument();
    expect(
      screen.getByText('You are not allowed to see this page')
    ).toBeInTheDocument();
  });
});
