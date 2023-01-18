import { RenderResult, screen, waitFor } from '@testing-library/react';
import { Provider } from 'jotai';

import { render } from '../testRenderer';
import CentreonLogoLight from '../../assets/centreon-logo-one-line-light.svg';

import Image from './Image';

jest.mock('../../assets/centreon-logo-one-line-light.svg');

const renderImage = (): RenderResult =>
  render(
    <Provider>
      <Image
        alt="test"
        fallback={<p>Loading...</p>}
        imagePath={CentreonLogoLight}
      />
    </Provider>
  );

const renderNotFoundImage = (): RenderResult =>
  render(
    <Provider>
      <Image
        alt="test"
        fallback={<p>Loading...</p>}
        imagePath="another_image"
      />
    </Provider>
  );

describe('useLoadImage', () => {
  it('displays the loaded image', async () => {
    renderImage();

    expect(screen.getByText('Loading...')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByAltText('test')).toBeInTheDocument();
    });
  });

  it('displays a not found image', async () => {
    renderNotFoundImage();

    expect(screen.getByText('Loading...')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByAltText('test')).toBeInTheDocument();
    });
  });
});
