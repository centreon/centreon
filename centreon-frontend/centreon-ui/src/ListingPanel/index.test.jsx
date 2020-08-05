import React from 'react';

import { render, fireEvent } from '@testing-library/react';

import PagePanel from '.';

describe(PagePanel, () => {
  it('displays given Header and sections', async () => {
    const Header = <>Header</>;
    const Sections = [
      {
        id: 0,
        expandable: false,
        Section: <>Non Expandable Section</>,
      },
      {
        id: 1,
        expandable: true,
        title: 'Expand me',
        Section: <>Expandable Section</>,
      },
    ];
    const { getByText } = render(
      <PagePanel Header={Header} Sections={Sections} active />,
    );

    expect(getByText('Header')).toBeInTheDocument();
    expect(getByText('Non Expandable Section')).toBeInTheDocument();
    expect(getByText('Expand me')).toBeInTheDocument();
    expect(getByText('Expandable Section')).toBeInTheDocument();
  });

  it('displays secondary Panel when secondary Panel bar is clicked', () => {
    const secondaryPanelComponent = <>Secondary Panel</>;

    const { baseElement, getByText, queryByText } = render(
      <PagePanel
        Header={<>Header</>}
        Sections={[]}
        active
        secondaryPanelComponent={secondaryPanelComponent}
      />,
    );

    expect(queryByText('Secondary Panel')).toBeNull();

    const svgs = baseElement.querySelectorAll('svg');

    // The first SVG corresponds to the close icon.
    const secondSvg = svgs[1];

    fireEvent.click(secondSvg);

    expect(getByText('Secondary Panel')).toBeInTheDocument();
  });
});
