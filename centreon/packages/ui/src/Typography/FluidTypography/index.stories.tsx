import { ComponentMeta } from '@storybook/react';

import { Box } from '@mui/material';

import FluidTypography, { FluidTypographyProps } from '.';

interface Props extends FluidTypographyProps {
  height?: string | number;
  width?: string | number;
}

const FluidTypographyTemplate = ({
  width = '100%',
  height = '100%',
  text,
  variant
}: Props): JSX.Element => {
  return (
    <Box sx={{ height, width }}>
      <FluidTypography text={text} variant={variant} />
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
  component: FluidTypographyTemplate,
  title: 'Fluid Typography'
} as ComponentMeta<typeof FluidTypographyTemplate>;

export const basic = FluidTypographyTemplate.bind({});
basic.args = {
  text: 'Hello world'
};

export const with200pxWidth = FluidTypographyTemplate.bind({});
with200pxWidth.args = {
  text: 'Hello world',
  width: '200px'
};

export const with20pxHeight = FluidTypographyTemplate.bind({});
with20pxHeight.args = {
  text: 'Hello world',
  width: '20px'
};

export const withLongText = FluidTypographyTemplate.bind({});
withLongText.args = {
  text: 'This is a very long text becaaaaaaause it has a lot to sayyyyy !!!!!!!!'
};

export const withH5Variant = FluidTypographyTemplate.bind({});
withH5Variant.args = {
  text: 'Hello world',
  variant: 'h5'
};
