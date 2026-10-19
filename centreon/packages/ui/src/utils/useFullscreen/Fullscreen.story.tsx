import { Button } from '@mui/material';

import { createStore, Provider } from 'jotai';
import { MemoryRouter } from 'react-router';

import { useFullscreen } from './useFullscreen';
import { useFullscreenListener } from './useFullscreenListener';

const Fullscreen = (): JSX.Element => {
  const { toggleFullscreen, fullscreenEnabled, isFullscreenActivated } =
    useFullscreen();

  return (
    <div
      data-fullscreenActivated={isFullscreenActivated}
      data-fullscreenEnabled={fullscreenEnabled}
      id="test"
    >
      <Button onClick={() => toggleFullscreen(document.body)}>
        {isFullscreenActivated ? 'Exit fullscreen' : 'Enter fullscreen'}
      </Button>
      <input id="input" />
      <textarea id="textarea" />
    </div>
  );
};

const Listener = (): JSX.Element => {
  useFullscreenListener();

  return <Fullscreen />;
};

export const Default = (): JSX.Element => (
  <MemoryRouter>
    <Provider store={createStore()}>
      <Listener />
    </Provider>
  </MemoryRouter>
);
