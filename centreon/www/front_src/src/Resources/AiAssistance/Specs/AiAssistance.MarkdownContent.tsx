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

    it('opens links in a new tab without a referrer', () => {
      render(<MarkdownContent content="[docs](https://example.com/docs)" />);

      const link = screen.getByRole('link', { name: 'docs' });

      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    });
  });
};
