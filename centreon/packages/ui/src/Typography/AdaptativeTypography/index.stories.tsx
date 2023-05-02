import { ComponentMeta } from '@storybook/react';

import { Box } from '@mui/material';

import AdaptativeTypography, { AdaptativeTypographyProps } from '.';

interface Props extends AdaptativeTypographyProps {
  height?: string | number;
  width?: string | number;
}

const AdaptativeTypographyTemplate = ({
  width = '100%',
  height = '100%',
  text,
  variant
}: Props): JSX.Element => {
  return (
    <Box sx={{ height, width }}>
      <AdaptativeTypography text={text} variant={variant} />
    </Box>
  );
};

export default {
  argTypes: {
    height: { control: 'text' },
    text: { control: 'text' },
    variant: { control: 'text' },
    width: { control: 'text' }
  },
  component: AdaptativeTypographyTemplate,
  title: 'Adaptative Typography'
} as ComponentMeta<typeof AdaptativeTypographyTemplate>;

export const basic = AdaptativeTypographyTemplate.bind({});
basic.args = {
  text: 'Hello world'
};

export const with200pxWidth = AdaptativeTypographyTemplate.bind({});
with200pxWidth.args = {
  text: 'Hello world',
  width: '200px'
};

export const with20pxHeight = AdaptativeTypographyTemplate.bind({});
with20pxHeight.args = {
  text: 'Hello world',
  width: '20px'
};

export const withLongText = AdaptativeTypographyTemplate.bind({});
withLongText.args = {
  text: 'This is a very long text becaaaaaaause it has a lot to sayyyyy !!!!!!!!'
};

export const withH5Variant = AdaptativeTypographyTemplate.bind({});
withH5Variant.args = {
  text: 'Hello world',
  variant: 'h5'
};
