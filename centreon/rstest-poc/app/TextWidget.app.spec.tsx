import { describe, expect, it } from '@rstest/core';

import Widget from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-text/src';
import { renderApp, screen } from './render';

/** Phase 0b port: a dashboard widget (render-only). */
describe('Text Widget (Rstest app POC)', () => {
  it('displays the widget', () => {
    renderApp(<Widget />);

    // The widget renders the text in two typography variants (preview + title).
    expect(screen.getAllByText('Hello world')[0]).toBeVisible();
  });
});
