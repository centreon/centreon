import initStoryshots from '@storybook/addon-storyshots';
import { imageSnapshot } from '@storybook/addon-storyshots-puppeteer';

const getMatchOptions = () => {
  return {
    failureThreshold: 0.2,
    failureThresholdType: 'percent',
  };
};
const beforeScreenshot = () => {
  return new Promise((resolve) =>
    setTimeout(() => {
      resolve();
    }, 600),
  );
};

initStoryshots({
  suite: 'Image StoryShots',
  test: imageSnapshot({
    storybookUrl: `file://${__dirname}/../.out`,
    getMatchOptions,
    beforeScreenshot,
  }),
});
