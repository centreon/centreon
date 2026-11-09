import { Button } from '@mui/material';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createStore, Provider } from 'jotai';
import { BrowserRouter } from 'react-router';

import { useFullscreen } from './useFullscreen';
import { router, useFullscreenListener } from './useFullscreenListener';

const labelEnterFullscreen = 'Enter fullscreen';
const labelExitFullscreen = 'Exit fullscreen';

const ChildComponent = (): JSX.Element => {
  const { toggleFullscreen, fullscreenEnabled, isFullscreenActivated } =
    useFullscreen();

  return (
    <div
      data-fullscreenActivated={isFullscreenActivated}
      data-fullscreenEnabled={fullscreenEnabled}
      id="test"
    >
      <Button onClick={() => toggleFullscreen(document.body)}>
        {isFullscreenActivated ? labelExitFullscreen : labelEnterFullscreen}
      </Button>
      <input id="input" />
      <textarea id="textarea" />
    </div>
  );
};

const TestComponent = (): JSX.Element => {
  useFullscreenListener();

  return <ChildComponent />;
};

// The real Fullscreen API is shared by the whole browser: with test files
// running in parallel pages, a request can be refused or exited by another
// page. It is faked here, with the same contract: the promises resolve, the
// fullscreen element is set and cleared, and `fullscreenchange` is fired.
const fakeFullscreenApi = (): void => {
  let fullscreenElement: Element | null = null;

  const changeFullscreenElement = (element: Element | null): Promise<void> => {
    fullscreenElement = element;
    document.dispatchEvent(new Event('fullscreenchange'));

    return Promise.resolve();
  };

  rs.spyOn(Document.prototype, 'fullscreenElement', 'get').mockImplementation(
    () => fullscreenElement
  );
  rs.spyOn(Document.prototype, 'fullscreenEnabled', 'get').mockReturnValue(
    true
  );
  rs.spyOn(Element.prototype, 'requestFullscreen').mockImplementation(function (
    this: Element
  ) {
    return changeFullscreenElement(this);
  });
  rs.spyOn(Document.prototype, 'exitFullscreen').mockImplementation(() =>
    changeFullscreenElement(null)
  );
};

const initialize = (): void => {
  fakeFullscreenApi();

  const store = createStore();

  const queryParameters = new Map();

  rs.spyOn(router, 'useSearchParams').mockImplementation(
    () =>
      queryParameters as unknown as ReturnType<typeof router.useSearchParams>
  );

  render(
    <BrowserRouter>
      <Provider store={store}>
        <TestComponent />
      </Provider>
    </BrowserRouter>
  );
};

const getTestElement = (): HTMLElement =>
  document.querySelector('#test') as HTMLElement;

const expectFullscreen = async (activated: boolean): Promise<void> => {
  await waitFor(() => {
    expect(getTestElement()).toHaveAttribute(
      'data-fullscreenActivated',
      `${activated}`
    );
  });
  expect(getTestElement()).toHaveAttribute('data-fullscreenEnabled', 'true');
};

describe('Fullscreen', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  afterEach(() => {
    rs.restoreAllMocks();
  });

  it('enters fullscreen mode when the button is clicked', async () => {
    initialize();

    await expectFullscreen(false);

    await userEvent.click(screen.getByText(labelEnterFullscreen));

    await expectFullscreen(true);

    await userEvent.click(screen.getByText(labelExitFullscreen));
  });

  it('exits fullscreen mode when the button is clicked', async () => {
    initialize();

    await expectFullscreen(false);

    await userEvent.click(screen.getByText(labelEnterFullscreen));

    await expectFullscreen(true);

    await userEvent.click(screen.getByText(labelExitFullscreen));

    await expectFullscreen(false);
  });

  it('toggles fullscreen mode when the corresponding shortcut is clicked', async () => {
    initialize();

    await expectFullscreen(false);

    await userEvent.keyboard('F');

    await expectFullscreen(true);

    await userEvent.keyboard('F');

    await expectFullscreen(false);
  });

  ['input', 'textarea'].forEach((tag) => {
    it(`cannot toggle fullscreen feature using the shortcut when ${tag === 'input' ? 'an' : 'a'} ${tag} is focused`, async () => {
      initialize();

      await expectFullscreen(false);

      // The spies (which call through) tell that the shortcut did not even
      // ask for a change.
      const requestFullscreen = rs.spyOn(document.body, 'requestFullscreen');
      const exitFullscreen = rs.spyOn(document, 'exitFullscreen');
      const field = document.querySelector(`#${tag}`) as HTMLInputElement;

      field.focus();

      await userEvent.keyboard('F');

      await expectFullscreen(false);
      expect(field).toHaveValue('F');
      expect(requestFullscreen).not.toHaveBeenCalled();
      expect(exitFullscreen).not.toHaveBeenCalled();
    });
  });
});
