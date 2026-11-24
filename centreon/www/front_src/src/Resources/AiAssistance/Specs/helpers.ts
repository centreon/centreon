import { getRequestCalls, interceptRequest, Method } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';

import { labelClose } from '../translatedLabels';

interface SlowFirstRequestProps {
  alias: string;
  // what the first request answers, once released
  first: { body: object; status?: number };
  // what the next requests answer, right away
  next: object;
  path: string;
}

// Holds the answer of the first request until the test releases it, so a
// newer request can supersede it.
export const interceptSlowFirstRequest = ({
  alias,
  first,
  next,
  path
}: SlowFirstRequestProps): (() => void) => {
  let release = (): void => undefined;
  const released = new Promise<void>((resolve) => {
    release = resolve;
  });
  let count = 0;

  interceptRequest(
    Method.POST,
    path,
    async () => {
      count += 1;

      if (count > 1) {
        return HttpResponse.json(next);
      }

      await released;

      return HttpResponse.json(first.body, { status: first.status ?? 200 });
    },
    alias
  );

  return release;
};

export const closeDialog = async (): Promise<void> => {
  await userEvent.click(
    await screen.findByRole('button', { name: labelClose })
  );

  await waitFor(() =>
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  );
};

// A disabled button gets no pointer event: its tooltip shows on the wrapper.
export const expectTooltip = async (
  button: HTMLElement,
  title: string
): Promise<void> => {
  await userEvent.hover(button.parentElement as HTMLElement);

  expect(await screen.findByRole('tooltip')).toHaveTextContent(title);
};

// Resolves once every request of the alias is answered, and the page had the
// time to apply the answers: for asserting that an answer is ignored.
export const waitForAllAnswers = async (alias: string): Promise<void> => {
  await waitFor(() =>
    expect(getRequestCalls(alias).every(({ answered }) => answered)).toBe(true)
  );

  await new Promise((resolve) => {
    setTimeout(resolve, 100);
  });
};

// Answers with a body that is not JSON, as a proxy error page would.
export const interceptUnreadableAnswer = ({
  alias,
  method = Method.POST,
  path
}: {
  alias: string;
  method?: Method;
  path: string;
}): void => {
  interceptRequest(
    method,
    path,
    () =>
      new HttpResponse('<html>Bad gateway</html>', {
        headers: { 'Content-Type': 'application/json' },
        status: 200
      }),
    alias
  );
};
