import React from 'react';
import { shallow } from 'enzyme';

import Subtitle from '.';

describe('Subtitle', () => {
  it('renders', () => {
    const label = 'label';
    const subtitleType = 'subtitle-type';
    const wrapper = shallow(
      <Subtitle label={label} subtitleType={subtitleType} />,
    );

    expect(wrapper.html()).toEqual(
      '<span class="custom-subtitle subtitle-type">label</span>',
    );
  });
});
