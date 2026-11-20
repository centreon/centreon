import { interceptAPIRequest, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import TestQueryProvider from '../api/TestQueryProvider';
import { Method } from '../api/useMutationQuery';
import { labelNextPage, labelPreviousPage } from '../Listing/translatedLabels';
import Pagination from './Pagination';
import { generateItems } from './utils';

const defaultTotalItems = 25;
const itemsPerPage = 6;
const totalPages = Math.ceil(defaultTotalItems / itemsPerPage);

interface InitializeProps {
  currentPage?: number;
  total?: number;
}

const initialize = ({
  total = defaultTotalItems,
  currentPage = 1
}: InitializeProps): void => {
  interceptAPIRequest({
    alias: 'list',
    method: Method.GET,
    path: '**/listing**',
    response: {
      meta: {
        limit: itemsPerPage,
        page: currentPage,
        total
      },
      result: generateItems(itemsPerPage)
    }
  });

  render(
    <div
      style={{
        alignItems: 'center',
        display: 'flex',
        height: '100vh',
        justifyContent: 'center',
        width: '100%'
      }}
    >
      <div
        style={{
          boxShadow: '2px 2px 4px rgba(0, 0, 0, 0.2)',
          height: '176px'
        }}
      >
        <TestQueryProvider>
          <Pagination
            api={{ baseEndpoint: '/test/listing', queryKey: ['test'] }}
          />
        </TestQueryProvider>
      </div>
    </div>
  );
};

// The six items of the page, in order.
const expectItems = async (): Promise<void> => {
  expect(await screen.findByText('Item Item Item 0')).toBeVisible();

  expect(
    screen.getAllByText(/^Item Item Item \d$/).map((item) => item.textContent)
  ).toEqual(generateItems(itemsPerPage).map(({ name }) => name));
};

const getRequestedPage = (url: URL): number =>
  Number(url.searchParams.get('page'));

describe('Pagination Component', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('render with correct initial state', async () => {
    initialize({});
    const { request } = await waitForRequest('@list');

    expect(await screen.findByTestId(labelPreviousPage)).toBeDisabled();

    expect(screen.getByTestId(labelNextPage)).toBeEnabled();

    expect(await screen.findByText(`Page 1/${totalPages}`)).toBeVisible();

    expect(getRequestedPage(request.url)).toBe(1);
    await expectItems();
  });

  it('hides pagination controls when only one page exists', async () => {
    initialize({ total: itemsPerPage });
    await waitForRequest('@list');
    // The items first, so the controls are checked once the answer is
    // applied (before, the page shows no controls either). The shared query
    // client may first show the page a previous test left in its cache.
    await expectItems();

    await waitFor(() => {
      expect(screen.queryByTestId(labelPreviousPage)).not.toBeInTheDocument();
    });
    expect(screen.queryByTestId(labelNextPage)).not.toBeInTheDocument();
    expect(screen.queryByText(/Page \d+\/\d+/)).not.toBeInTheDocument();
  });

  it('navigates forward through pages correctly', async () => {
    initialize({});
    await waitForRequest('@list');

    expect(await screen.findByText(`Page 1/${totalPages}`)).toBeVisible();

    for (let index = 0; index < totalPages - 1; index += 1) {
      await userEvent.click(screen.getByTestId(labelNextPage));
      const { request } = await waitForRequest('@list');

      expect(
        await screen.findByText(`Page ${index + 2}/${totalPages}`)
      ).toBeVisible();
      expect(getRequestedPage(request.url)).toBe(index + 2);
    }

    await waitFor(() => {
      expect(screen.getByTestId(labelNextPage)).toBeDisabled();
    });

    expect(screen.getByTestId(labelPreviousPage)).toBeEnabled();
    await expectItems();
  });

  it('navigates backward through pages correctly', async () => {
    initialize({});

    await waitForRequest('@list');

    for (let index = 0; index < totalPages - 1; index += 1) {
      await screen.findByText(`Page ${index + 1}/${totalPages}`);
      await userEvent.click(screen.getByTestId(labelNextPage));

      await waitForRequest('@list');
    }

    await screen.findByText(`Page ${totalPages}/${totalPages}`);

    for (let index = 0; index < totalPages - 1; index += 1) {
      await userEvent.click(screen.getByTestId(labelPreviousPage));
      const { request } = await waitForRequest('@list');

      expect(
        await screen.findByText(`Page ${totalPages - index - 1}/${totalPages}`)
      ).toBeVisible();
      expect(getRequestedPage(request.url)).toBe(totalPages - index - 1);
    }

    await waitFor(() => {
      expect(screen.getByTestId(labelPreviousPage)).toBeDisabled();
    });

    expect(screen.getByTestId(labelNextPage)).toBeEnabled();
    await expectItems();
  });

  it('enables both buttons when on middle page', async () => {
    initialize({});

    await waitForRequest('@list');

    await userEvent.click(await screen.findByTestId(labelNextPage));

    await waitForRequest('@list');

    await waitFor(() => {
      expect(screen.getByTestId(labelPreviousPage)).toBeEnabled();
    });
    expect(screen.getByTestId(labelNextPage)).toBeEnabled();

    expect(await screen.findByText(`Page 2/${totalPages}`)).toBeVisible();
    await expectItems();
  });
});
