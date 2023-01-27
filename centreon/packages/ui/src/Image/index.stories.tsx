import { ComponentStory } from '@storybook/react';

import LoadingSkeleton from '../LoadingSkeleton';
import CentreonLogoLight from '../../assets/centreon-logo-light.svg';
import NotAuthorized from '../../assets/not-authorized-template-background-light.svg';

import Image, { ImageVariant } from './Image';

export default {
  argTypes: {
    alt: { control: 'text' },
    height: { control: 'number' },
    width: { control: 'number' }
  },
  component: Image,

  title: 'Image'
};

const Template: ComponentStory<typeof Image> = (args) => (
  <Image
    {...args}
    fallback={<LoadingSkeleton />}
    imagePath={CentreonLogoLight}
  />
);

const size = 50;

export const basic = Template.bind({});
basic.args = {
  alt: 'Centreon logo light'
};

export const multipleImages = (): JSX.Element => {
  return (
    <div
      style={{
        columnGap: '16px',
        display: 'grid',
        gridTemplateColumns: `repeat(2, ${size}px)`
      }}
    >
      <Image
        alt="Centreon logo light"
        fallback={<LoadingSkeleton height={size} width={size} />}
        height={size}
        imagePath={CentreonLogoLight}
        width={size}
      />
      <Image
        alt="Not authorized"
        fallback={<LoadingSkeleton height={size} width={size} />}
        height={size}
        imagePath={NotAuthorized}
        width={size}
      />
      <Image
        alt="Centreon logo light"
        fallback={<LoadingSkeleton height={size} width={size} />}
        height={size}
        imagePath={CentreonLogoLight}
        width={size}
      />
      <Image
        alt="Not authorized"
        fallback={<LoadingSkeleton height={size} width={size} />}
        height={size}
        imagePath={NotAuthorized}
        width={size}
      />
    </div>
  );
};

export const withWidthAndHeight = Template.bind({});
withWidthAndHeight.args = {
  alt: 'Centreon logo light',
  fallback: <LoadingSkeleton height={size} width={size} />,
  height: size,
  imagePath: CentreonLogoLight,
  width: size
};

export const imageNotFound = (): JSX.Element => (
  <Image
    alt="Not found alt text"
    fallback={<LoadingSkeleton height={size} width={size} />}
    height={size}
    imagePath="not-found"
    width={size}
  />
);

export const variantContain = Template.bind({});
variantContain.args = {
  alt: 'Centreon logo light',
  height: size,
  variant: ImageVariant.Contain,
  width: size
};
