import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';

import { MarkdownContent } from '../MarkdownContent';

export default (): void => {
  describe('MarkdownContent', () => {
    it('shows the text of an image instead of loading it', () => {
      const { container } = render(
        <MarkdownContent content="See ![a chart](https://example.com/p?d=secret) here" />
      );

      expect(container.querySelector('img')).toBeNull();
      expect(screen.getByText('a chart')).toBeInTheDocument();
    });

    it('does not render the raw HTML of an answer', () => {
      const { container } = render(
        <MarkdownContent
          content={
            'Before <img src="https://example.com/p?d=secret" onerror="alert(1)"> after'
          }
        />
      );

      expect(container.querySelector('img')).toBeNull();
      expect(container.querySelector('[onerror]')).toBeNull();
      expect(screen.getByText('Before', { exact: false })).toBeInTheDocument();
    });

    it('does not keep a javascript link active', () => {
      const { container } = render(
        <MarkdownContent content="[click me](javascript:alert(document.cookie))" />
      );

      expect(container.querySelector('a[href^="javascript"]')).toBeNull();
      expect(
        screen
          .queryAllByRole('link')
          .every(
            (link) =>
              !(link.getAttribute('href') ?? '')
                .toLowerCase()
                .includes('javascript')
          )
      ).toBe(true);
      expect(screen.getByText('click me')).toBeInTheDocument();
    });

    it('opens links in a new tab without a referrer', () => {
      render(<MarkdownContent content="[docs](https://example.com/docs)" />);

      const link = screen.getByRole('link', { name: 'docs' });

      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    });
  });
};
