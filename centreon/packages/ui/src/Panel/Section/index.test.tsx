import { render, fireEvent } from '../../testRenderer';

import SectionPanel from '.';

describe(SectionPanel, () => {
  it('displays given Header and sections', async () => {
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
      <SectionPanel header={header} sections={sections} onClose={jest.fn()} />
    );

    expect(getByText('Header')).toBeInTheDocument();
    expect(getByText('Non Expandable Section')).toBeInTheDocument();
    expect(getByText('Expand me')).toBeInTheDocument();
    expect(getByText('Expandable Section')).toBeInTheDocument();
  });

  it('displays secondary Panel when secondary Panel bar is clicked', () => {
    const secondaryPanel = <>Secondary Panel</>;

    const { baseElement, getByText, queryByText } = render(
      <SectionPanel
        header={<>Header</>}
        secondaryPanel={secondaryPanel}
        sections={[]}
        onClose={jest.fn()}
      />
    );

    expect(queryByText('Secondary Panel')).toBeNull();

    const svgs = baseElement.querySelectorAll('svg');

    // The first SVG corresponds to the close icon.
    const secondSvg = svgs[1];

    fireEvent.click(secondSvg);

    expect(getByText('Secondary Panel')).toBeInTheDocument();
  });
});
