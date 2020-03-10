import initStoryshots from '@storybook/addon-storyshots';
import { imageSnapshot } from '@storybook/addon-storyshots-puppeteer';

jest.unmock('axios');

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

const getStoryKindRegex = () => {
  const config = JSON.parse(process.env.TEST_CONFIGURATION);
  if (!config.story) {
    return null;
  }

  return new RegExp(`^(${config.story || ''})$`, 'g');
};

initStoryshots({
  suite: 'Image StoryShots',
  storyKindRegex: getStoryKindRegex(),
  test: imageSnapshot({
    storybookUrl: `file://${__dirname}/../.out`,
    getMatchOptions,
    beforeScreenshot,
  }),
});
