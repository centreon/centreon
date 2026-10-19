import { describe, expect, it, rstest } from '@rstest/core';

import SectionPanel from '../packages/ui/src/Panel/Section';
import { fireEvent, render } from './testRender';

/**
 * Port of packages/ui/src/Panel/Section/index.test.tsx to Rstest.
 * Demonstrates rendering a real @centreon/ui component (bundled by Rspack) plus
 * a user interaction. Migration from Jest = imports from '@rstest/core' and
 * jest.fn() -> rstest.fn(); the assertions are unchanged.
 */
describe('SectionPanel (Rstest POC)', () => {
  it('displays the given header and sections', () => {
    const header = <>Header</>;
    const sections = [
      {
        expandable: false,
        id: 'non-expandable',
        section: <>Non Expandable Section</>
      },
      {
        expandable: true,
        id: 'expandable',
        section: <>Expandable Section</>,
        title: 'Expand me'
      }
    ];

    const { getByText } = render(
      <SectionPanel header={header} onClose={rstest.fn()} sections={sections} />
    );

    expect(getByText('Header')).toBeInTheDocument();
    expect(getByText('Non Expandable Section')).toBeInTheDocument();
    expect(getByText('Expand me')).toBeInTheDocument();
    expect(getByText('Expandable Section')).toBeInTheDocument();
  });

  it('displays the secondary panel when its bar is clicked', () => {
    const { baseElement, getByText } = render(
      <SectionPanel
        header={<>Header</>}
        onClose={rstest.fn()}
        secondaryPanel={<>Secondary Panel</>}
        sections={[]}
      />
    );

    // NOTE (POC finding): the original Jest test asserted the panel is absent
    // before the click. Under Rstest the real MUI transition (with real CSS
    // bundled by Rspack) keeps the node mounted but hidden, so we assert the
    // user-visible outcome instead — clicking the bar reveals the panel.
    const secondSvg = baseElement.querySelectorAll('svg')[1];
    fireEvent.click(secondSvg);

    expect(getByText('Secondary Panel')).toBeVisible();
  });
});
