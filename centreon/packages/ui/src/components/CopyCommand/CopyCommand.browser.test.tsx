import { ThemeMode, type User, userAtom } from '@centreon/ui-context';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createStore, Provider } from 'jotai';

import SnackbarProvider from '../../Snackbar/SnackbarProvider';
import ThemeProvider from '../../ThemeProvider';
import CopyCommand, { type CopyCommandProps } from './CopyCommand';

const initialize = (props: CopyCommandProps & { theme?: ThemeMode }): void => {
  const store = createStore();
  store.set(userAtom, { themeMode: props.theme || ThemeMode.light } as User);
  render(
    <Provider store={store}>
      <ThemeProvider>
        <SnackbarProvider>
          <CopyCommand {...props} />
        </SnackbarProvider>
      </ThemeProvider>
    </Provider>
  );
};

const jsonText = `{
  "number": 1,
  "boolean": true,
  "array": [
    {
      "string": "text"
    }
  ]
}`;

const getCode = (): HTMLElement => {
  const code = document.querySelector('pre');

  if (!code) {
    throw new Error('No highlighted code block');
  }

  return code;
};

// The highlighted token of the given highlight.js class and exact text, with
// its computed colour.
const expectToken = ({
  className,
  color,
  text
}: {
  className: string;
  color: string;
  text: string;
}): void => {
  const token = Array.from(
    getCode().querySelectorAll<HTMLElement>(`.${className}`)
  ).find((element) => element.textContent === text);

  expect(token).toBeDefined();
  expect(token).toBeVisible();
  expect(getComputedStyle(token as HTMLElement).color).toBe(color);
};

// The language chip shows the language, and only it.
const expectLanguageChip = (language: string): void => {
  expect(screen.getByText(language, { selector: 'div' })).toHaveTextContent(
    new RegExp(`^${language}$`)
  );
};

describe('CopyCommand', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays bash code when props are set', async () => {
    initialize({
      commandToCopy: 'echo "hello" | grep "hel"',
      language: 'bash',
      text: `# a simple command
echo "hello" | grep "hel"`
    });

    expect(await screen.findByText('# a simple command')).toBeVisible();
    expect(screen.getByText('echo')).toBeVisible();
    expect(screen.getByText('bash')).toBeVisible();
    expect(screen.getByTestId('Copy command')).toBeVisible();

    expect(getCode()).toHaveTextContent(
      /^# a simple command echo "hello" \| grep "hel"$/
    );
    expectLanguageChip('bash');
    expectToken({
      className: 'hljs-comment',
      color: 'rgb(106, 115, 125)',
      text: '# a simple command'
    });
    expectToken({
      className: 'hljs-built_in',
      color: 'rgb(227, 98, 9)',
      text: 'echo'
    });
    expectToken({
      className: 'hljs-string',
      color: 'rgb(3, 47, 98)',
      text: '"hello"'
    });
    expectToken({
      className: 'hljs-string',
      color: 'rgb(3, 47, 98)',
      text: '"hel"'
    });
  });

  it('displays yaml code when props are set', async () => {
    initialize({
      commandToCopy: 'echo "hello" | grep "hel"',
      language: 'yaml',
      text: `key:
  with:
    input: "input"`
    });

    expect(await screen.findByText('key:')).toBeVisible();
    expect(screen.getByText('"input"')).toBeVisible();
    expect(screen.getByText('yaml')).toBeVisible();

    expectLanguageChip('yaml');
    expect(screen.getByTestId('Copy command')).toBeVisible();
    // The indentation is kept, as non-breaking spaces.
    expect(getCode().textContent).toMatch(/\n\u00a0+with:\n\u00a0+input:/);
    expectToken({
      className: 'hljs-attr',
      color: 'rgb(0, 92, 197)',
      text: 'key:'
    });
    expectToken({
      className: 'hljs-attr',
      color: 'rgb(0, 92, 197)',
      text: 'input:'
    });
    expectToken({
      className: 'hljs-string',
      color: 'rgb(3, 47, 98)',
      text: '"input"'
    });
  });

  it('displays php code when props are set', async () => {
    initialize({
      commandToCopy: 'echo "hello" | grep "hel"',
      language: 'php',
      text: `<?php
echo 'Hello ' . htmlspecialchars($_POST["name"]) . '!';
?>`
    });

    expect(await screen.findByText('echo')).toBeVisible();
    expect(screen.getByText('"name"')).toBeVisible();
    expect(screen.getByText('php')).toBeVisible();

    expectLanguageChip('php');
    expect(screen.getByTestId('Copy command')).toBeVisible();
    expect(getCode()).toHaveTextContent(
      `<?php echo 'Hello ' . htmlspecialchars($_POST["name"]) . '!'; ?>`
    );
    expectToken({
      className: 'hljs-keyword',
      color: 'rgb(215, 58, 73)',
      text: 'echo'
    });
    expectToken({
      className: 'hljs-title',
      color: 'rgb(111, 66, 193)',
      text: 'htmlspecialchars'
    });
    expectToken({
      className: 'hljs-variable',
      color: 'rgb(0, 92, 197)',
      text: '$_POST'
    });
  });

  it('displays json code when props are set', async () => {
    initialize({
      commandToCopy: 'echo "hello" | grep "hel"',
      language: 'json',
      text: jsonText
    });

    expect(await screen.findByText('"number"')).toBeVisible();
    expect(screen.getByText('true')).toBeVisible();
    expect(screen.getByText('json')).toBeVisible();

    expectLanguageChip('json');
    expect(screen.getByTestId('Copy command')).toBeVisible();
    expectToken({
      className: 'hljs-attr',
      color: 'rgb(0, 92, 197)',
      text: '"number"'
    });
    expectToken({
      className: 'hljs-number',
      color: 'rgb(0, 92, 197)',
      text: '1'
    });
    expectToken({
      className: 'hljs-keyword',
      color: 'rgb(215, 58, 73)',
      text: 'true'
    });
    expectToken({
      className: 'hljs-string',
      color: 'rgb(3, 47, 98)',
      text: '"text"'
    });
  });

  it('does not display the copy button when the corresponding prop is not passed', async () => {
    initialize({
      language: 'json',
      text: jsonText
    });

    expect(await screen.findByText('"number"')).toBeVisible();
    expect(screen.queryByTestId('Copy command')).not.toBeInTheDocument();

    expectLanguageChip('json');
  });

  it('displays the highlighted code in dark mode when the theme is changed', async () => {
    initialize({
      language: 'json',
      text: jsonText,
      theme: ThemeMode.dark
    });

    await waitFor(() => {
      const keyword = document.querySelector('.hljs-keyword');

      expect(keyword).not.toBeNull();
      expect(getComputedStyle(keyword as Element).color).toBe(
        'rgb(255, 123, 114)'
      );
    });

    // The rest of the dark palette.
    expectToken({
      className: 'hljs-attr',
      color: 'rgb(121, 192, 255)',
      text: '"number"'
    });
    expectToken({
      className: 'hljs-string',
      color: 'rgb(165, 214, 255)',
      text: '"text"'
    });
    expectLanguageChip('json');
    expect(screen.queryByTestId('Copy command')).not.toBeInTheDocument();
  });

  it('copies the command to the clipboard when the button is clicked', async () => {
    const writeText = rs
      .spyOn(navigator.clipboard, 'writeText')
      .mockResolvedValue(undefined);

    initialize({
      commandToCopy: 'echo "hello" | grep "hel"',
      language: 'bash',
      text: `# a simple command
echo "hello" | grep "hel"`
    });

    await userEvent.click(await screen.findByTestId('Copy command'));

    await waitFor(() => {
      expect(writeText).toHaveBeenCalledTimes(1);
    });
    expect(writeText).toHaveBeenCalledWith('echo "hello" | grep "hel"');

    expect(screen.getByText('# a simple command')).toBeVisible();
    expectLanguageChip('bash');
  });
});
