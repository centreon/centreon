import { ComponentMeta } from '@storybook/react';

import image from '../../assets/not-authorized-template-background-light.svg';

import WallpaperPage from '.';

export default {
  component: WallpaperPage,
  title: 'WallpaperPage'
} as ComponentMeta<typeof WallpaperPage>;

export const normal = (): JSX.Element => (
  <WallpaperPage wallpaperAlt="wallpaper presentation" wallpaperSource={image}>
    <p>Hello</p>
  </WallpaperPage>
);
